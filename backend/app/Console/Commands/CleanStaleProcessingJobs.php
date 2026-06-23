<?php

namespace App\Console\Commands;

use App\Models\LabReport;
use App\Models\ReportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanStaleProcessingJobs extends Command
{
    protected $signature = 'clinex:clean-stale-jobs
                            {--threshold=120 : Minutes after which a processing job is considered stale}
                            {--dry-run : Show what would be cleaned without making changes}';

    protected $description = 'Reset lab reports and batches stuck in "processing" status beyond the threshold';

    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('threshold');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subMinutes($thresholdMinutes);

        $this->info("Looking for jobs stuck in 'processing' for more than {$thresholdMinutes} minutes...");

        // Find stale lab reports
        $staleReports = LabReport::where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->get();

        // Find stale batches
        $staleBatches = ReportBatch::where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->get();

        $reportCount = $staleReports->count();
        $batchCount = $staleBatches->count();

        if ($reportCount === 0 && $batchCount === 0) {
            $this->info('No stale processing jobs found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$reportCount} stale report(s) and {$batchCount} stale batch(es).");

        if ($dryRun) {
            foreach ($staleReports as $report) {
                $this->line("  [DRY RUN] Would reset LabReport #{$report->id} (stuck since {$report->updated_at})");
            }
            foreach ($staleBatches as $batch) {
                $this->line("  [DRY RUN] Would reset ReportBatch #{$batch->id} (stuck since {$batch->updated_at})");
            }
            return self::SUCCESS;
        }

        // Reset stale reports
        if ($reportCount > 0) {
            LabReport::where('status', 'processing')
                ->where('updated_at', '<', $cutoff)
                ->update([
                    'status' => 'failed',
                    'processing_error' => "Stale: automatically reset after {$thresholdMinutes} minutes with no progress",
                    'processed_at' => now(),
                ]);

            $this->info("Reset {$reportCount} stale lab report(s) to 'failed'.");
        }

        // Reset stale batches
        if ($batchCount > 0) {
            ReportBatch::where('status', 'processing')
                ->where('updated_at', '<', $cutoff)
                ->update([
                    'status' => 'failed',
                    'processing_completed_at' => now(),
                ]);

            $this->info("Reset {$batchCount} stale batch(es) to 'failed'.");
        }

        Log::info('Cleaned stale processing jobs', [
            'stale_reports' => $reportCount,
            'stale_batches' => $batchCount,
            'threshold_minutes' => $thresholdMinutes,
        ]);

        return self::SUCCESS;
    }
}
