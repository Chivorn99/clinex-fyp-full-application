<?php

namespace App\Jobs;

use App\Models\ReportBatch;
use App\Models\LabReport;
use App\Models\ReportTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessLabReportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportBatch;
    public $timeout = 7200; // 2 hours timeout for large batches (20 files * 3-4 mins)

    public function __construct(ReportBatch $reportBatch)
    {
        $this->reportBatch = $reportBatch;
    }

    public function handle()
    {
        try {
            $this->reportBatch->update([
                'status' => 'processing',
                'processing_started_at' => now()
            ]);

            $reports = $this->reportBatch->labReports()->whereIn('status', ['uploaded', 'failed'])->get();
            if ($reports->isEmpty()) {
                $this->markBatchCompleted();
                return;
            }

            Log::info('Starting persistent batch processing', [
                'batch_id' => $this->reportBatch->id,
                'total_reports' => $reports->count()
            ]);

            // 1. Gather files and parameters
            $firstReport = $reports->first();
            $documentType = $firstReport->document_type;
            $templateId = $firstReport->template_id;

            $filePaths = [];
            $reportMap = []; // map filename to LabReport
            $tempS3Files = []; // track temp files for S3 cleanup
            $isS3 = config('filesystems.disks.private.driver') === 's3';

            foreach ($reports as $report) {
                $report->update(['status' => 'processing']);

                if ($isS3) {
                    // S3/Spaces: download to a temp file for the Python OCR script
                    $absolutePath = storage_path('app/tmp_' . $report->id . '_' . basename($report->storage_path));
                    file_put_contents($absolutePath, Storage::disk('private')->get($report->storage_path));
                    $tempS3Files[] = $absolutePath;
                } else {
                    $absolutePath = Storage::disk('private')->path($report->storage_path);
                }

                $filePaths[] = $absolutePath;
                $reportMap[basename($absolutePath)] = $report;
            }

            // Write file list
            $fileListPath = storage_path('app/private/batch_' . $this->reportBatch->id . '_files.txt');
            file_put_contents($fileListPath, implode("\n", $filePaths));

            // 2. Build Python Command
            $pythonScript = base_path('scripts/python/document_ocr.py');
            $pythonPath = config('app.python_path', 'python3');

            $command = [
                $pythonPath,
                $pythonScript,
                '--file-list',
                $fileListPath,
                '--stream',
                '--workers',
                '1' // Force sequential for GPU memory safety
            ];

            if ($documentType) {
                $command[] = '--document-type';
                $command[] = $documentType;
            }

            $templateFile = null;
            $activeTemplate = $templateId ? ReportTemplate::find($templateId) : ReportTemplate::getActive();
            if ($activeTemplate) {
                $templateFile = storage_path('app/private/template_batch_' . $this->reportBatch->id . '.json');
                file_put_contents($templateFile, json_encode($activeTemplate->toPythonPayload()));
                $command[] = '--template';
                $command[] = $templateFile;
            }

            $process = new Process($command);
            $process->setTimeout($this->timeout);

            // Pass Env
            $credentialsEnv = env('GOOGLE_APPLICATION_CREDENTIALS', '');
            $credentialsPath = str_starts_with($credentialsEnv, 'app/') ? storage_path($credentialsEnv) : storage_path('app/' . $credentialsEnv);
            
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
                'KIRI_OCR_ENABLED' => env('KIRI_OCR_ENABLED', 'false'),
                'KIRI_OCR_DECODE_METHOD' => env('KIRI_OCR_DECODE_METHOD', 'accurate'),
                'KIRI_OCR_CONFIDENCE_THRESHOLD' => env('KIRI_OCR_CONFIDENCE_THRESHOLD', '0.7'),
                'KIRI_OCR_DEVICE' => env('KIRI_OCR_DEVICE', 'auto'),
                'OLLAMA_ENABLED' => env('OLLAMA_ENABLED', 'false'),
                'OLLAMA_HOST' => env('OLLAMA_HOST', 'http://ollama:11434'),
                'OLLAMA_MODEL' => env('OLLAMA_MODEL', 'phi3:mini'),
                'OLLAMA_TIMEOUT' => env('OLLAMA_TIMEOUT', '600'),
            ]);
            $process->setEnv($env);

            // 3. Run and Stream Results
            $bufferStr = "";
            $process->run(function ($type, $buffer) use (&$bufferStr, $reportMap) {
                if ($type === Process::OUT) {
                    $bufferStr .= $buffer;
                    // Try to process complete lines
                    $lines = explode("\n", $bufferStr);
                    // The last element is either empty or a partial line
                    $bufferStr = array_pop($lines);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;

                        $resultArr = json_decode($line, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($resultArr)) {
                            // The stream outputs an array of 1 item: [ { "source_file": ..., "success": ... } ]
                            $result = isset($resultArr[0]) ? $resultArr[0] : $resultArr;
                            
                            if (isset($result['source_file']) && isset($reportMap[$result['source_file']])) {
                                $this->processReportResult($reportMap[$result['source_file']], $result);
                            }
                        }
                    }
                }
            });

            // Clean up
            @unlink($fileListPath);
            if ($templateFile) @unlink($templateFile);

            // Clean up temp S3 download files
            foreach ($tempS3Files as $tmpFile) {
                @unlink($tmpFile);
            }

            $this->markBatchCompleted();

        } catch (\Exception $e) {
            // Mark any still-processing reports as failed
            $this->reportBatch->labReports()
                ->where('status', 'processing')
                ->update([
                    'status' => 'failed',
                    'processing_error' => 'Batch failed: ' . $e->getMessage(),
                    'processed_at' => now(),
                ]);

            $this->reportBatch->update([
                'status' => 'failed',
                'processing_completed_at' => now()
            ]);

            Log::error('Batch processing failed', [
                'batch_id' => $this->reportBatch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure (timeout, out-of-memory, queue worker kill, etc.)
     */
    public function failed(?\Throwable $exception): void
    {
        // Clean up any reports still stuck as 'processing'
        $this->reportBatch->labReports()
            ->where('status', 'processing')
            ->update([
                'status' => 'failed',
                'processing_error' => 'Job failed: ' . ($exception ? $exception->getMessage() : 'Unknown error'),
                'processed_at' => now(),
            ]);

        $this->reportBatch->update([
            'status' => 'failed',
            'processing_completed_at' => now(),
        ]);

        Log::error('ProcessLabReportBatch job failed', [
            'batch_id' => $this->reportBatch->id,
            'error' => $exception ? $exception->getMessage() : 'Unknown',
        ]);
    }

    private function processReportResult(LabReport $report, array $result)
    {
        try {
            $isSuccess = isset($result['success']) ? $result['success'] : !isset($result['error']);

            if ($isSuccess) {
                // We instantiate a dummy object to use normalizeExtractedData 
                // Alternatively, move normalization to a Trait or Service.
                // But we can just duplicate or use dependency injection.
                // Actually, ProcessSingleLabReport has the normalization logic.
                // Let's instantiate it to use its method.
                $singleJob = new ProcessSingleLabReport($report);
                // We make normalizeExtractedData public in ProcessSingleLabReport or just call it if we can
                // PHP might complain if it's protected.
                // Let's implement it here directly to avoid issues.
            }

            $updateData = [
                'processed_at' => now(),
                'processing_time' => $result['processingTime'] ?? null,
                'status' => $isSuccess ? 'processed' : 'failed',
                'extracted_data' => $isSuccess ? $this->normalizeData($result) : null,
                'processing_error' => $isSuccess ? null : ($result['error'] ?? 'Unknown error'),
            ];

            $report->update($updateData);

            if ($isSuccess) {
                $this->reportBatch->increment('processed_reports');
            } else {
                $this->reportBatch->increment('failed_reports');
            }
        } catch (\Exception $e) {
            Log::error('Failed to save individual report result', ['report_id' => $report->id, 'error' => $e->getMessage()]);
            $report->update(['status' => 'failed', 'processing_error' => 'DB Save Error: ' . $e->getMessage()]);
            $this->reportBatch->increment('failed_reports');
        }
    }

    private function normalizeData(array $extractedData): array
    {
        if (isset($extractedData['testResults']) && is_array($extractedData['testResults'])) {
            $flatResults = [];
            foreach ($extractedData['testResults'] as $category => $tests) {
                if (is_array($tests)) {
                    foreach ($tests as $test) {
                        if (is_array($test)) {
                            $test['category'] = $category;
                            $flatResults[] = $test;
                        }
                    }
                }
            }
            if (!empty($flatResults)) {
                $extractedData['testResults'] = $flatResults;
            }
        }
        return $extractedData;
    }

    private function markBatchCompleted()
    {
        // Catch any orphaned reports still stuck as 'processing'
        // (e.g. Python didn't return a result line for them)
        $orphanedReports = $this->reportBatch->labReports()
            ->where('status', 'processing')
            ->get();

        foreach ($orphanedReports as $orphan) {
            $orphan->update([
                'status' => 'failed',
                'processing_error' => 'No result received from OCR pipeline',
                'processed_at' => now(),
            ]);
            $this->reportBatch->increment('failed_reports');
            Log::warning('Orphaned processing report marked as failed', [
                'report_id' => $orphan->id,
                'batch_id' => $this->reportBatch->id,
            ]);
        }

        $this->reportBatch->refresh();
        $finalStatus = ($this->reportBatch->failed_reports > 0 && $this->reportBatch->processed_reports == 0) 
            ? 'failed' 
            : 'completed';

        $this->reportBatch->update([
            'status' => $finalStatus,
            'processing_completed_at' => now()
        ]);
    }
}