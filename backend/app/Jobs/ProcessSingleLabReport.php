<?php

namespace App\Jobs;

use App\Models\LabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessSingleLabReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $labReport;
    public $timeout = 300; // 5 minutes timeout per file

    public function __construct(LabReport $labReport)
    {
        $this->labReport = $labReport;
    }

    public function handle()
    {
        try {
            // Update status to processing
            $this->labReport->update(['status' => 'processing']);

            // Get file path
            $filePath = Storage::disk('private')->path($this->labReport->storage_path);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Use the optimized Python script
            $pythonScript = base_path('scripts/python/document_ocr.py');

            $command = [
                config('app.python_path', 'python'),
                $pythonScript,
                '--file',
                $filePath,
                '--output-format',
                'json'
            ];

            Log::info('Processing single lab report', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'command' => implode(' ', $command)
            ]);

            $process = new Process($command);
            $process->setTimeout(180); // 3 minutes per file
            $process->mustRun();
            $output = $process->getOutput();

            if (empty($output)) {
                throw new \Exception('No output received from OCR script');
            }

            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON output: ' . json_last_error_msg());
            }

            // Handle array result (script returns array with single item)
            if (is_array($result) && isset($result[0])) {
                $result = $result[0];
            }

            // Update lab report with results
            $isSuccess = isset($result['success']) ? $result['success'] : !isset($result['error']);
            
            $updateData = [
                'processed_at' => now(),
                'processing_time' => $result['processingTime'] ?? null,
                'status' => $isSuccess ? 'processed' : 'failed',
                'extracted_data' => $isSuccess ? $result : null,
                'processing_error' => $isSuccess ? null : ($result['error'] ?? 'Unknown error'),
            ];

            $this->labReport->update($updateData);

            Log::info('Lab report processed successfully', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'status' => $updateData['status'],
                'processing_time' => $updateData['processing_time']
            ]);

        } catch (\Exception $e) {
            $this->labReport->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Lab report processing failed', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'error' => $e->getMessage()
            ]);

            // Don't re-throw to prevent job retry (we've already marked as failed)
        }
    }
}