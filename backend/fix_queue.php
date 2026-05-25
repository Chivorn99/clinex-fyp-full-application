<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all reports that haven't been verified yet
$reports = App\Models\LabReport::whereNull('verified_at')->get();

$count = 0;
foreach($reports as $report) {
    // Reset status to uploaded so the UI knows it's being processed again
    $report->update([
        'status' => 'uploaded',
        'processing_error' => null
    ]);
    
    // Ensure the parent batch also reflects the processing state
    if ($report->batch && $report->batch->status !== 'processing') {
        $report->batch->update(['status' => 'processing']);
    }
    
    // Dispatch to the queue
    App\Jobs\ProcessSingleLabReport::dispatch($report)->onQueue('lab-reports');
    $count++;
}

echo "Successfully queued {$count} unverified reports for re-processing!\n";
