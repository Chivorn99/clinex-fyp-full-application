<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reports = App\Models\LabReport::whereNull('verified_at')->take(3)->get();
$count = 0;
foreach ($reports as $report) {
    $report->update(['status' => 'uploaded', 'processing_error' => null]);
    App\Jobs\ProcessSingleLabReport::dispatch($report)->onQueue('lab-reports');
    $count++;
    echo "Dispatched: {$report->original_filename}\n";
}
echo "\nTotal dispatched: {$count} reports for speed test\n";
