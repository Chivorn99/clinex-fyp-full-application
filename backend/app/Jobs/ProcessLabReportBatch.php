<?php

namespace App\Jobs;

use App\Models\ReportBatch;
use App\Models\ReportTemplate;
use App\Jobs\ProcessSingleLabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessLabReportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportBatch;
    public $timeout = 1800; // 30 minutes timeout for large batches

    public function __construct(ReportBatch $reportBatch)
    {
        $this->reportBatch = $reportBatch;
    }

    public function handle()
    {
        try {
            // Update batch status
            $this->reportBatch->update([
                'status' => 'processing',
                'processing_started_at' => now()
            ]);

            Log::info('Starting batch OCR processing', [
                'batch_id' => $this->reportBatch->id,
                'total_reports' => $this->reportBatch->total_reports
            ]);

            // Use optimized parallel processing with Python script
            $this->processWithPythonScript();

        } catch (\Exception $e) {
            $this->reportBatch->update([
                'status' => 'failed',
                'processing_completed_at' => now()
            ]);

            Log::error('Batch processing failed', [
                'batch_id' => $this->reportBatch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Process batch using optimized Python script with parallel processing.
     */
    private function processWithPythonScript()
    {
        // Define the batch directory path
        $batchDirectory = Storage::disk('private')->path('lab_reports/batch_' . $this->reportBatch->id);
        
        // Check if directory exists
        if (!is_dir($batchDirectory)) {
            throw new \Exception("Batch directory not found: {$batchDirectory}");
        }

        // Use optimized script with parallel processing
        $pythonScript = base_path('scripts/python/document_ocr.py');
        
        // Determine optimal worker count (max 3 for Google Document AI limits)
        $workerCount = min(3, $this->reportBatch->total_reports);

        // Resolve Python binary: prefer python3 (Docker), fall back to python
        $pythonPath = config('app.python_path', 'python3');
        
        $command = [
            $pythonPath,
            $pythonScript,
            '--batch',
            $batchDirectory,
            '--workers',
            (string) $workerCount,
            '--output-format',
            'json'
        ];

        // Load active report template for LLM extraction
        $templateFile = null;
        $activeTemplate = ReportTemplate::getActive();
        if ($activeTemplate) {
            $templateFile = storage_path('app/private/template_batch_' . $this->reportBatch->id . '.json');
            file_put_contents($templateFile, json_encode($activeTemplate->toPythonPayload()));
            $command[] = '--template';
            $command[] = $templateFile;
        }

        Log::info('Starting parallel OCR processing', [
            'batch_id' => $this->reportBatch->id,
            'command' => implode(' ', $command),
            'worker_count' => $workerCount,
            'batch_directory' => $batchDirectory
        ]);

        // Process with timeout for large batches
        $process = new Process($command);
        $process->setTimeout(1200); // 20 minutes for large batches
        $process->setIdleTimeout(300); // 5 minutes idle timeout

        // Pass environment variables so Python can find credentials
        // The .env GOOGLE_APPLICATION_CREDENTIALS is relative to the project root (e.g. 'app/google/...')
        // storage_path() points to /var/www/html/storage, so we need storage_path(env_value)
        $credentialsEnv = env('GOOGLE_APPLICATION_CREDENTIALS', '');
        // Handle both 'app/google/...' and 'google/...' formats
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
            'OLLAMA_TIMEOUT' => env('OLLAMA_TIMEOUT', '180'),
        ]);
        $process->setEnv($env);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('OCR Python script failed', [
                'batch_id' => $this->reportBatch->id,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
                'stdout_preview' => substr($process->getOutput(), 0, 500),
            ]);
            throw new \Exception('OCR script failed: ' . $process->getErrorOutput());
        }

        $output = $process->getOutput();
        
        if (empty($output)) {
            throw new \Exception('No output received from OCR script');
        }

        $results = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON output from Python script', [
                'batch_id' => $this->reportBatch->id,
                'json_error' => json_last_error_msg(),
                'output_preview' => substr($output, 0, 500)
            ]);
            throw new \Exception('Invalid JSON output: ' . json_last_error_msg());
        }

        Log::info('OCR processing completed', [
            'batch_id' => $this->reportBatch->id,
            'results_count' => count($results)
        ]);

        // Clean up temp template file
        if ($templateFile && file_exists($templateFile)) {
            unlink($templateFile);
        }

        // Process results and update database
        $this->updateLabReportsFromResults($results);

        // Update final batch status
        $this->updateFinalBatchStatus();
    }

    /**
     * Update lab reports from Python script results.
     */
    private function updateLabReportsFromResults(array $results)
    {
        foreach ($results as $result) {
            try {
                // Get the lab report by filename
                $filename = basename($result['source_file'] ?? '');
                
                if (empty($filename)) {
                    Log::warning('Empty filename in result', [
                        'result' => $result,
                        'batch_id' => $this->reportBatch->id
                    ]);
                    continue;
                }

                $labReport = $this->reportBatch->labReports()
                    ->where('stored_filename', $filename)
                    ->first();

                if (!$labReport) {
                    Log::warning('Lab report not found for file', [
                        'file' => $filename, 
                        'batch_id' => $this->reportBatch->id
                    ]);
                    continue;
                }

                // Check if processing was successful
                $isSuccess = isset($result['success']) ? $result['success'] : !isset($result['error']);

                $updateData = [
                    'processed_at' => now(),
                    'processing_time' => $result['processingTime'] ?? null,
                    'raw_ocr_text' => $result['rawText'] ?? null
                ];

                if ($isSuccess) {
                    // Normalize: flatten grouped test_results → flat testResults array
                    $normalizedResult = $this->normalizeExtractedData($result);

                    $updateData = array_merge($updateData, [
                        'extracted_data' => $normalizedResult,
                        'status' => 'processed',
                        'processing_error' => null
                    ]);
                } else {
                    $updateData = array_merge($updateData, [
                        'status' => 'failed',
                        'processing_error' => $result['error'] ?? 'Unknown processing error'
                    ]);
                }

                $labReport->update($updateData);

                Log::info('Lab report updated from batch processing', [
                    'lab_report_id' => $labReport->id,
                    'filename' => $filename,
                    'status' => $updateData['status'],
                    'processing_time' => $updateData['processing_time'] ?? 'N/A'
                ]);

            } catch (\Exception $e) {
                Log::error('Error updating lab report from batch result', [
                    'result' => $result,
                    'batch_id' => $this->reportBatch->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Normalize extracted data from Python script output.
     *
     * The Python OCR script may return test results in two formats:
     * - New grouped format: { "test_results": { "biochemistry": [...], "hematology": [...] } }
     * - Legacy flat format: { "testResults": [ { "category": "...", ... }, ... ] }
     *
     * This method normalizes both into the flat "testResults" format that the
     * frontend verification page and backend verification controller expect.
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
                    // Ensure category is set from the panel key if missing
                    if (empty($test['category'])) {
                        $test['category'] = strtoupper($panel);
                    }
                    $flatTests[] = $test;
                }
            }
            $result['testResults'] = $flatTests;
            unset($result['test_results']); // Remove grouped key to avoid confusion
        } else {
            // Neither format found — set empty array
            $result['testResults'] = [];
        }

        return $result;
    }

    /**
     * Update final batch status and counts.
     */
    private function updateFinalBatchStatus()
    {
        $this->reportBatch->refresh();
        
        // Fix any reports that have extracted_data but are still marked as 'failed'
        // (can happen from previous broken runs)
        $this->reportBatch->labReports()
            ->where('status', 'failed')
            ->whereNotNull('extracted_data')
            ->update(['status' => 'processed', 'processing_error' => null]);

        // Get actual counts from database
        $statusCounts = $this->reportBatch->labReports()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $processedCount = $statusCounts['processed'] ?? 0;
        $failedCount = $statusCounts['failed'] ?? 0;
        $totalProcessed = $processedCount + $failedCount;

        // Determine final status:
        // - 'completed' if all reports processed successfully
        // - 'completed_with_errors' if some failed but at least one succeeded
        // - 'failed' only if ALL reports failed (zero succeeded)
        // - 'partial' if not all reports have been processed yet
        $finalStatus = 'completed';
        if ($totalProcessed < $this->reportBatch->total_reports) {
            $finalStatus = 'partial';
        } elseif ($failedCount > 0 && $processedCount > 0) {
            $finalStatus = 'completed_with_errors';
        } elseif ($failedCount > 0 && $processedCount === 0) {
            $finalStatus = 'failed';
        }

        $this->reportBatch->update([
            'processed_reports' => $processedCount,
            'failed_reports' => $failedCount,
            'status' => $finalStatus,
            'processing_completed_at' => now()
        ]);

        Log::info('Batch processing completed', [
            'batch_id' => $this->reportBatch->id,
            'total_reports' => $this->reportBatch->total_reports,
            'processed' => $processedCount,
            'failed' => $failedCount,
            'final_status' => $finalStatus,
            'processing_duration' => \Carbon\Carbon::parse($this->reportBatch->processing_started_at)->diffInSeconds(now()) . ' seconds'
        ]);
    }
}