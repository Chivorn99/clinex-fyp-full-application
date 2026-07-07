<?php

/*
|--------------------------------------------------------------------------
| Clinex — Patient API Tests
|--------------------------------------------------------------------------
| Tests for patient CRUD, search, and report associations.
*/

use App\Models\User;
use App\Models\Patient;
use App\Models\LabReport;
use App\Models\ReportBatch;

// ─── List Patients ───────────────────────────────────────────────────

it('lists all patients', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    Patient::factory()->count(5)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/patients');

    $response->assertOk();
});

it('rejects patient listing without auth', function () {
    $response = $this->getJson('/api/patients');
    $response->assertStatus(401);
});

// ─── Create Patient ──────────────────────────────────────────────────

it('creates a new patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/patients', [
            'patient_id' => 'PT00501',
            'name' => 'Test Patient',
            'age' => 30,
            'gender' => 'M',
            'phone' => '0123456789',
            'email' => 'patient@test.com',
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('patients', [
        'patient_id' => 'PT00501',
        'name' => 'Test Patient',
    ]);
});

// ─── Search Patients ─────────────────────────────────────────────────

it('searches patients by name', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Patient::factory()->create(['name' => 'Sok Chea']);
    Patient::factory()->create(['name' => 'Chan Dara']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/patients/search?q=Sok');

    $response->assertOk();
});

// ─── Show Patient ────────────────────────────────────────────────────

it('shows a single patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $patient = Patient::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/patients/{$patient->id}");

    $response->assertOk();
});

it('returns 404 for non-existent patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/patients/99999');

    $response->assertStatus(404);
});

// ─── Update Patient ──────────────────────────────────────────────────

it('updates a patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $patient = Patient::factory()->create(['name' => 'Old Name']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/patients/{$patient->id}", [
            'name' => 'Updated Name',
            'age' => 35,
            'gender' => 'F',
        ]);

    $response->assertOk();
    $patient->refresh();
    expect($patient->name)->toBe('Updated Name');
});

// ─── Delete Patient ──────────────────────────────────────────────────

it('deletes a patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $patient = Patient::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/patients/{$patient->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
});

// ─── Patient Lab Reports ─────────────────────────────────────────────

it('returns lab reports for a specific patient', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $patient = Patient::factory()->create();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);

    LabReport::factory()->count(3)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'patient_id' => $patient->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/patients/{$patient->id}/lab-reports");

    $response->assertOk();
});
