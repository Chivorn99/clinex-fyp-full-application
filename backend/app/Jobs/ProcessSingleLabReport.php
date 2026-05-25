<?php

namespace App\Jobs;

use App\Models\LabReport;
use App\Models\ReportTemplate;
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
    public $timeout = 600; // 10 minutes timeout per file

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

            // Resolve Python binary: prefer python3 (Docker), fall back to python
            $pythonPath = config('app.python_path', 'python3');

            $command = [
                $pythonPath,
                $pythonScript,
                '--file',
                $filePath,
                '--output-format',
                'json'
            ];

            // Load active report template for LLM extraction
            $templateFile = null;
            $activeTemplate = ReportTemplate::getActive();
            if ($activeTemplate) {
                $templateFile = storage_path('app/private/template_' . $this->labReport->id . '.json');
                file_put_contents($templateFile, json_encode($activeTemplate->toPythonPayload()));
                $command[] = '--template';
                $command[] = $templateFile;
            }

            Log::info('Processing single lab report', [
                'lab_report_id' => $this->labReport->id,
                'filename' => $this->labReport->original_filename,
                'command' => implode(' ', $command)
            ]);

            $process = new Process($command);
            $process->setTimeout(600); // 10 minutes per file

            // Pass environment variables so Python can find credentials
            $credentialsEnv = env('GOOGLE_APPLICATION_CREDENTIALS', '');
            $credentialsPath = str_starts_with($credentialsEnv, 'app/')
                ? storage_path($credentialsEnv)
                : storage_path('app/' . $credentialsEnv);

            $env = array_merge(getenv() ?: [], [
                'GOOGLE_APPLICATION_CREDENTIALS' => $credentialsPath,
                'GOOGLE_CLOUD_PROJECT_ID' => env('GOOGLE_CLOUD_PROJECT_ID', ''),
                'GOOGLE_CLOUD_LOCATION' => env('GOOGLE_CLOUD_LOCATION', ''),
                'GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID' => env('GOOGLE_CLOUD_DOCUMENT_AI_PROCESSOR_ID', ''),
                'PADDLE_OCR_ENABLED' => env('PADDLE_OCR_ENABLED', 'false'),
                'PADDLE_OCR_LANGUAGE' => env('PADDLE_OCR_LANGUAGE', 'ch'),
                'PADDLE_OCR_CONFIDENCE_THRESHOLD' => env('PADDLE_OCR_CONFIDENCE_THRESHOLD', '0.85'),
                'PADDLE_OCR_DEVICE' => env('PADDLE_OCR_DEVICE', 'auto'),
                'PADDLE_OCR_GPU_ID' => env('PADDLE_OCR_GPU_ID', '0'),
                'OLLAMA_ENABLED' => env('OLLAMA_ENABLED', 'false'),
                'OLLAMA_HOST' => env('OLLAMA_HOST', 'http://ollama:11434'),
                'OLLAMA_MODEL' => env('OLLAMA_MODEL', 'phi3:mini'),
                'OLLAMA_TIMEOUT' => env('OLLAMA_TIMEOUT', '600'),
            ]);
            $process->setEnv($env);

            $process->mustRun();
            $output = $process->getOutput();

            // Clean up temp template file
            if ($templateFile && file_exists($templateFile)) {
                unlink($templateFile);
            }

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

            // Normalize: flatten grouped test_results → flat testResults array
            if ($isSuccess) {
                $result = $this->normalizeExtractedData($result);
            }

            $updateData = [
                'processed_at' => now(),
                'processing_time' => $result['processingTime'] ?? null,
                'status' => $isSuccess ? 'processed' : 'failed',
                'extracted_data' => $isSuccess ? $result : null,
                'raw_ocr_text' => $isSuccess ? ($result['rawText'] ?? null) : ($result['rawText'] ?? null),
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

        // Check if this was the last report in the batch and update batch status if needed
        $this->checkBatchCompletion();
    }

    /**
     * Normalize extracted data from Python script output.
     *
     * The Python OCR script may return test results in two formats:
     * - New grouped format: { "test_results": { "biochemistry": [...], "hematology": [...] } }
     * - Legacy flat format: { "testResults": [ { "category": "...", ... }, ... ] }
     *
     * Normalizes both into the flat "testResults" format expected by the frontend and backend.
     */
    private function normalizeExtractedData(array $result): array
    {
        // Already has flat testResults — nothing to do
        if (isset($result['testResults']) && is_array($result['testResults'])) {
            return $result;
        }

        // Has grouped test_results — flatten into testResults
        $groupedResults = $result['test_results'] ?? null;
        if (is_array($groupedResults)) {
            $flatTests = [];
            foreach ($groupedResults as $panel => $tests) {
                if (!is_array($tests)) {
                    continue;
                }
                foreach ($tests as $test) {
                    if (!is_array($test)) {
                        continue;
                    }
                    if (empty($test['category'])) {
                        $test['category'] = strtoupper($panel);
                    }
                    $flatTests[] = $test;
                }
            }
            $result['testResults'] = $flatTests;
            unset($result['test_results']);
        } else {
            $result['testResults'] = [];
        }

        return $result;
    }

    /**
     * Check if all reports in the batch are processed and update final batch status.
     */
    private function checkBatchCompletion()
    {
        if (!$this->labReport->batch_id) {
            return;
        }

        $batch = $this->labReport->batch;
        
        // Use a lock to prevent race conditions when multiple jobs finish at the exact same time
        $lock = \Illuminate\Support\Facades\Cache::lock('batch_completion_' . $batch->id, 10);
        
        if ($lock->get()) {
            try {
                $batch->refresh();
                
                if ($batch->status !== 'processing') {
                    return;
                }

                $statusCounts = $batch->labReports()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $processedCount = $statusCounts['processed'] ?? 0;
                $failedCount = $statusCounts['failed'] ?? 0;
                $totalProcessed = $processedCount + $failedCount;

                if ($totalProcessed >= $batch->total_reports) {
                    $finalStatus = 'completed';
                    if ($failedCount > 0 && $processedCount > 0) {
                        $finalStatus = 'completed_with_errors';
                    } elseif ($failedCount > 0 && $processedCount === 0) {
                        $finalStatus = 'failed';
                    }

                    $batch->update([
                        'processed_reports' => $processedCount,
                        'failed_reports' => $failedCount,
                        'status' => $finalStatus,
                        'processing_completed_at' => now()
                    ]);

                    Log::info('Batch processing completed from single job', [
                        'batch_id' => $batch->id,
                        'final_status' => $finalStatus
                    ]);
                }
            } finally {
                $lock->release();
            }
        }
    }
}