<?php

/*
|--------------------------------------------------------------------------
| Clinex — Lab Report API Tests
|--------------------------------------------------------------------------
| Tests for lab report listing, details, verification, and export.
*/

use App\Models\User;
use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\Patient;
use App\Models\ExtractedData;

// ─── Helper ──────────────────────────────────────────────────────────

function authenticatedUser(string $role = 'lab_technician'): array
{
    $user = User::factory()->create(['role' => $role]);
    $token = $user->createToken('test')->plainTextToken;
    return [$user, $token];
}

// ─── List Lab Reports ────────────────────────────────────────────────

it('lists lab reports for authenticated user', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->count(3)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/lab-reports');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['data'],
        ]);
});

it('filters lab reports by status', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);

    LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'status' => 'processed',
        'processed_at' => now(),
    ]);

    LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'status' => 'failed',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/lab-reports?status=processed');

    $response->assertOk();
    // All returned reports should have 'processed' status
    $data = $response->json('data.data');
    foreach ($data as $report) {
        expect($report['status'])->toBe('processed');
    }
});

it('filters lab reports by batch_id', function () {
    [$user, $token] = authenticatedUser();
    $batch1 = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $batch2 = ReportBatch::factory()->create(['uploaded_by' => $user->id]);

    LabReport::factory()->count(2)->create(['batch_id' => $batch1->id, 'uploaded_by' => $user->id]);
    LabReport::factory()->create(['batch_id' => $batch2->id, 'uploaded_by' => $user->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/lab-reports?batch_id={$batch1->id}");

    $response->assertOk();
    $data = $response->json('data.data');
    expect(count($data))->toBe(2);
});

it('rejects lab reports listing without auth', function () {
    $response = $this->getJson('/api/lab-reports');
    $response->assertStatus(401);
});

// ─── Show Single Lab Report ─────────────────────────────────────────

it('shows a single lab report with details', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->processed()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/lab-reports/{$report->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'status', 'original_filename'],
        ]);
});

it('returns 404 for non-existent lab report', function () {
    [$user, $token] = authenticatedUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/lab-reports/99999');

    $response->assertStatus(404);
});

// ─── Verification ────────────────────────────────────────────────────

it('verifies a processed lab report successfully', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->processed()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $verifiedData = [
        'documentType' => 'lab_report',
        'patientInfo' => [
            'name' => 'Verified Patient',
            'patientId' => 'PT99999',
            'age' => '30',
            'gender' => 'M',
            'phone' => '0123456789',
        ],
        'labInfo' => [
            'labId' => 'LAB001',
            'requestedBy' => 'Dr. Test',
            'requestedDate' => '2026-01-15',
            'collectedDate' => '2026-01-15',
            'analysisDate' => '2026-01-15',
            'validatedBy' => 'Technician A',
        ],
        'testResults' => [
            [
                'testName' => 'Glucose',
                'result' => '5.2',
                'unit' => 'mmol/L',
                'referenceRange' => '3.9-6.1',
                'category' => 'BIOCHEMISTRY',
                'flag' => null,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/lab-reports/{$report->id}/verify", [
            'verified_data' => $verifiedData,
            'notes' => 'All values confirmed correct',
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    // Check report is now verified
    $report->refresh();
    expect($report->status)->toBe('verified');
    expect($report->verified_at)->not->toBeNull();
    expect($report->verified_by)->toBe($user->id);

    // Check patient was created
    $this->assertDatabaseHas('patients', ['name' => 'Verified Patient']);

    // Check extracted data was stored
    $this->assertDatabaseHas('extracted_data', [
        'lab_report_id' => $report->id,
        'test_name' => 'Glucose',
        'result' => '5.2',
    ]);
});

it('rejects verification of non-processed report', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'status' => 'uploaded',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/lab-reports/{$report->id}/verify", [
            'verified_data' => [
                'patientInfo' => ['name' => 'Test'],
                'labInfo' => [],
                'testResults' => [],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Lab report must be processed before verification']);
});

it('rejects double verification', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->verified()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'status' => 'processed', // keep status as processed but verified_at is set
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/lab-reports/{$report->id}/verify", [
            'verified_data' => [
                'patientInfo' => ['name' => 'Test'],
                'labInfo' => [],
                'testResults' => [],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'This report has already been verified']);
});

// ─── Test Results ────────────────────────────────────────────────────

it('retrieves test results for a lab report', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->verified()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    ExtractedData::factory()->count(5)->create([
        'lab_report_id' => $report->id,
        'is_verified' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/lab-reports/{$report->id}/test-results");

    $response->assertOk();
});

// ─── Delete ──────────────────────────────────────────────────────────

it('deletes a lab report', function () {
    [$user, $token] = authenticatedUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/lab-reports/{$report->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('lab_reports', ['id' => $report->id]);
});
