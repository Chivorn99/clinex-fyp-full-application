<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSingleLabReport;
use App\Models\LabReport;
use Illuminate\Console\Command;

class ReprocessReports extends Command
{
    protected $signature = 'clinex:reprocess
                            {--batch= : Batch ID to reprocess}
                            {--status=processed : Only reprocess reports with this status}
                            {--all : Reprocess all processed reports}
                            {--sync : Process synchronously instead of queuing}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Re-queue existing lab reports for OCR reprocessing with the latest script';

    public function handle(): int
    {
        $batchId = $this->option('batch');
        $status = $this->option('status');
        $all = $this->option('all');
        $sync = $this->option('sync');

        $query = LabReport::query();

        if ($batchId) {
            $query->where('batch_id', $batchId);
            $this->info("Filtering by batch ID: {$batchId}");
        }

        if (!$all) {
            $query->where('status', $status);
            $this->info("Filtering by status: {$status}");
        }

        $reports = $query->get();

        if ($reports->isEmpty()) {
            $this->warn('No reports found matching the criteria.');
            return 0;
        }

        $this->info("Found {$reports->count()} report(s) to reprocess.");

        if (!$this->option('force') && !$this->confirm("Reprocess {$reports->count()} report(s)?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $count = 0;
        foreach ($reports as $report) {
            // Reset status so it gets picked up
            $report->update([
                'status' => 'processing',
                'processing_error' => null,
            ]);

            if ($sync) {
                $this->info("Processing: {$report->original_filename}");
                ProcessSingleLabReport::dispatchSync($report);
            } else {
                ProcessSingleLabReport::dispatch($report);
            }
            $count++;
        }

        $mode = $sync ? 'synchronously' : 'queued';
        $this->info("✅ {$count} report(s) {$mode} for reprocessing.");

        if (!$sync) {
            $this->info('Run `php artisan queue:work` to process the queue.');
        }

        return 0;
    }
}
