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

            $reports = $this->reportBatch->labReports()->whereIn('status', ['uploaded', 'failed', 'pending'])->get();
            if ($reports->isEmpty()) {
                $this->markBatchCompleted();
                return;
            }

            Log::info('Starting batch processing via ProcessSingleLabReport', [
                'batch_id' => $this->reportBatch->id,
                'total_reports' => $reports->count()
            ]);

            foreach ($reports as $report) {
                $report->update(['status' => 'processing']);
                ProcessSingleLabReport::dispatchSync($report);
            }

        } catch (\Exception $e) {
            Log::error('Batch processing failed', [
                'batch_id' => $this->reportBatch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->failed($e);
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