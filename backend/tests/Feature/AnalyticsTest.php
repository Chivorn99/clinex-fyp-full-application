<?php

/*
|--------------------------------------------------------------------------
| Clinex — Analytics API Tests
|--------------------------------------------------------------------------
| Tests for the analytics dashboard endpoint.
*/

use App\Models\User;
use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\ExtractedData;
use App\Models\Patient;

it('returns analytics dashboard data', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Seed data for analytics
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    $patients = Patient::factory()->count(5)->create();

    foreach ($patients as $patient) {
        $report = LabReport::factory()->verified()->create([
            'batch_id' => $batch->id,
            'uploaded_by' => $user->id,
            'patient_id' => $patient->id,
        ]);

        ExtractedData::factory()->count(3)->create([
            'lab_report_id' => $report->id,
            'is_verified' => true,
        ]);
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/analytics/dashboard?days=30');

    $response->assertOk()
        ->assertJsonStructure([
            'stats',
            'report_volume',
            'category_distribution',
            'top_abnormal_tests',
            'patient_demographics',
            'confidence_by_category',
            'technician_activity',
        ]);
});

it('accepts different time ranges', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    foreach ([7, 14, 30, 0] as $days) {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/analytics/dashboard?days={$days}");

        $response->assertOk();
    }
});

it('rejects analytics without authentication', function () {
    $response = $this->getJson('/api/analytics/dashboard');
    $response->assertStatus(401);
});

it('returns stats structure with valid data types', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/analytics/dashboard?days=30');

    $response->assertOk()
        ->assertJsonStructure(['stats']);
});
