<?php

/*
|--------------------------------------------------------------------------
| Clinex — Unit Tests: ReportBatch Model
|--------------------------------------------------------------------------
*/

use App\Models\ReportBatch;
use App\Models\LabReport;
use App\Models\User;

it('belongs to an uploader', function () {
    $user = User::factory()->create();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);

    expect($batch->uploader)->toBeInstanceOf(User::class);
    expect($batch->uploader->id)->toBe($user->id);
});

it('has many lab reports', function () {
    $batch = ReportBatch::factory()->create();
    LabReport::factory()->count(5)->create(['batch_id' => $batch->id]);

    expect($batch->labReports)->toHaveCount(5);
});

it('calculates progress percentage', function () {
    $batch = ReportBatch::factory()->create([
        'total_reports' => 10,
        'processed_reports' => 5,
    ]);

    expect($batch->getProgressPercentage())->toBe(50.0);
});

it('returns 0 progress for empty batch', function () {
    $batch = ReportBatch::factory()->create([
        'total_reports' => 0,
        'processed_reports' => 0,
    ]);

    expect($batch->getProgressPercentage())->toBe(0.0);
});

it('detects completed status', function () {
    $batch = ReportBatch::factory()->create(['status' => 'completed']);
    expect($batch->isComplete())->toBeTrue();

    $batch2 = ReportBatch::factory()->create(['status' => 'processing']);
    expect($batch2->isComplete())->toBeFalse();
});

it('detects processing status', function () {
    $batch = ReportBatch::factory()->create(['status' => 'processing']);
    expect($batch->isProcessing())->toBeTrue();

    $batch2 = ReportBatch::factory()->create(['status' => 'pending']);
    expect($batch2->isProcessing())->toBeFalse();
});
