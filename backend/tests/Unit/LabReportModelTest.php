<?php

/*
|--------------------------------------------------------------------------
| Clinex — Unit Tests: LabReport Model
|--------------------------------------------------------------------------
| Tests for model relationships, scopes, and computed attributes.
*/

use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\User;
use App\Models\Patient;
use App\Models\ExtractedData;

it('belongs to a batch', function () {
    $batch = ReportBatch::factory()->create();
    $report = LabReport::factory()->create(['batch_id' => $batch->id]);

    expect($report->batch)->toBeInstanceOf(ReportBatch::class);
    expect($report->batch->id)->toBe($batch->id);
});

it('belongs to an uploader', function () {
    $user = User::factory()->create();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    expect($report->uploader)->toBeInstanceOf(User::class);
    expect($report->uploader->id)->toBe($user->id);
});

it('has uploadedBy alias relationship', function () {
    $user = User::factory()->create();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    expect($report->uploadedBy)->toBeInstanceOf(User::class);
    expect($report->uploadedBy->id)->toBe($user->id);
});

it('belongs to a patient when assigned', function () {
    $patient = Patient::factory()->create();
    $batch = ReportBatch::factory()->create();
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'patient_id' => $patient->id,
    ]);

    expect($report->patient)->toBeInstanceOf(Patient::class);
    expect($report->patient->id)->toBe($patient->id);
});

it('has many extracted data', function () {
    $batch = ReportBatch::factory()->create();
    $report = LabReport::factory()->create(['batch_id' => $batch->id]);
    ExtractedData::factory()->count(5)->create(['lab_report_id' => $report->id]);

    expect($report->extractedData)->toHaveCount(5);
});

it('scopes by status', function () {
    $batch = ReportBatch::factory()->create();
    LabReport::factory()->count(3)->create([
        'batch_id' => $batch->id,
        'status' => 'processed',
        'processed_at' => now(),
    ]);
    LabReport::factory()->count(2)->create([
        'batch_id' => $batch->id,
        'status' => 'failed',
    ]);

    expect(LabReport::withStatus('processed')->count())->toBe(3);
    expect(LabReport::withStatus('failed')->count())->toBe(2);
});

it('scopes by batch', function () {
    $batch1 = ReportBatch::factory()->create();
    $batch2 = ReportBatch::factory()->create();

    LabReport::factory()->count(3)->create(['batch_id' => $batch1->id]);
    LabReport::factory()->count(2)->create(['batch_id' => $batch2->id]);

    expect(LabReport::byBatch($batch1->id)->count())->toBe(3);
    expect(LabReport::byBatch($batch2->id)->count())->toBe(2);
});

it('formats file size correctly', function () {
    $batch = ReportBatch::factory()->create();

    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'file_size' => 2097152,
    ]); // 2 MB
    expect($report->formatted_file_size)->toContain('MB');

    $report2 = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'file_size' => 512000,
    ]);
    expect($report2->formatted_file_size)->toContain('KB');
});

it('returns Unknown for null file size', function () {
    $batch = ReportBatch::factory()->create();
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'file_size' => null,
    ]);
    expect($report->formatted_file_size)->toBe('Unknown');
});

it('casts extracted_data as json', function () {
    $batch = ReportBatch::factory()->create();
    $data = ['testResults' => [['testName' => 'Glucose', 'result' => '5.2']]];

    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'extracted_data' => $data,
    ]);

    $report->refresh();
    expect($report->extracted_data)->toBeArray();
    expect($report->extracted_data['testResults'][0]['testName'])->toBe('Glucose');
});
