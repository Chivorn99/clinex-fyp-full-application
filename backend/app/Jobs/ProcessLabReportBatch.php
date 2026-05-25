<?php

namespace App\Jobs;

use App\Models\ReportBatch;
use App\Jobs\ProcessSingleLabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

            Log::info('Starting batch processing (dispatching individual jobs)', [
                'batch_id' => $this->reportBatch->id,
                'total_reports' => $this->reportBatch->total_reports
            ]);

            $reports = $this->reportBatch->labReports()->whereIn('status', ['uploaded', 'failed'])->get();

            foreach ($reports as $report) {
                ProcessSingleLabReport::dispatch($report)->onQueue('lab-reports');
            }

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
}