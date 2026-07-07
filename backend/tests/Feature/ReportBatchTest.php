<?php

/*
|--------------------------------------------------------------------------
| Clinex — Report Batch API Tests
|--------------------------------------------------------------------------
| Tests for batch creation, listing, status, duplicate detection, and processing.
*/

use App\Models\User;
use App\Models\LabReport;
use App\Models\ReportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── Helper ──────────────────────────────────────────────────────────

function authUser(string $role = 'lab_technician'): array
{
    $user = User::factory()->create(['role' => $role]);
    $token = $user->createToken('test')->plainTextToken;
    return [$user, $token];
}

// ─── List Batches ────────────────────────────────────────────────────

it('lists batches for authenticated user', function () {
    [$user, $token] = authUser();
    ReportBatch::factory()->count(3)->create(['uploaded_by' => $user->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/batches');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['data'],
        ]);
});

it('filters batches by status', function () {
    [$user, $token] = authUser();
    ReportBatch::factory()->create(['uploaded_by' => $user->id, 'status' => 'pending']);
    ReportBatch::factory()->create(['uploaded_by' => $user->id, 'status' => 'completed']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/batches?status=completed');

    $response->assertOk();
});

it('searches batches by name', function () {
    [$user, $token] = authUser();
    ReportBatch::factory()->create(['uploaded_by' => $user->id, 'name' => 'Blood Test Batch']);
    ReportBatch::factory()->create(['uploaded_by' => $user->id, 'name' => 'Urine Test Batch']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/batches?search=Blood');

    $response->assertOk();
});

it('rejects batch listing without auth', function () {
    $response = $this->getJson('/api/batches');
    $response->assertStatus(401);
});

// ─── Show Single Batch ──────────────────────────────────────────────

it('shows a single batch with reports', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->count(2)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/batches/{$batch->id}");

    $response->assertOk();
});

// ─── Upload Batch ────────────────────────────────────────────────────

it('uploads a batch with single PDF file', function () {
    Storage::fake('private');
    [$user, $token] = authUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => [UploadedFile::fake()->create('report.pdf', 500, 'application/pdf')],
            'document_type' => 'lab_report',
            'auto_process' => 'false',
        ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);
});

it('uploads a batch with multiple files', function () {
    Storage::fake('private');
    [$user, $token] = authUser();

    $files = [];
    for ($i = 0; $i < 3; $i++) {
        $files[] = UploadedFile::fake()->create("report_{$i}.pdf", 500, 'application/pdf');
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => $files,
            'document_type' => 'lab_report',
            'auto_process' => 'false',
        ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);
});

it('rejects upload with invalid file type', function () {
    [$user, $token] = authUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => [UploadedFile::fake()->create('malware.exe', 500)],
            'document_type' => 'lab_report',
        ]);

    $response->assertStatus(422);
});

it('rejects upload without files', function () {
    [$user, $token] = authUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'document_type' => 'lab_report',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['files']);
});

it('rejects upload with more than 20 files', function () {
    [$user, $token] = authUser();

    $files = [];
    for ($i = 0; $i < 21; $i++) {
        $files[] = UploadedFile::fake()->create("report_{$i}.pdf", 100, 'application/pdf');
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => $files,
            'document_type' => 'lab_report',
        ]);

    $response->assertStatus(422);
});

it('requires document_type field', function () {
    [$user, $token] = authUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => [UploadedFile::fake()->create('report.pdf', 500, 'application/pdf')],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document_type']);
});

it('accepts consultation document type', function () {
    Storage::fake('private');
    [$user, $token] = authUser();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches', [
            'files' => [UploadedFile::fake()->create('consult.pdf', 500, 'application/pdf')],
            'document_type' => 'consultation',
            'auto_process' => 'false',
        ]);

    $response->assertStatus(201);
});

// ─── Batch Status ────────────────────────────────────────────────────

it('returns batch processing status', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->processing()->create(['uploaded_by' => $user->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/batches/{$batch->id}/status");

    $response->assertOk();
});

// ─── Duplicate Check ─────────────────────────────────────────────────

it('checks for duplicate filenames', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
        'original_filename' => 'existing_report.pdf',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/batches/check-duplicates', [
            'filenames' => ['existing_report.pdf', 'new_report.pdf'],
        ]);

    $response->assertOk();
});

// ─── Delete Batch ────────────────────────────────────────────────────

it('deletes a batch and its reports', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->count(2)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/batches/{$batch->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('report_batches', ['id' => $batch->id]);
});

// ─── Verification Endpoints ─────────────────────────────────────────

it('returns reports for verification in a batch', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->processed()->count(3)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/batches/{$batch->id}/reports-for-verification");

    $response->assertOk();
});

it('returns all reports pending verification', function () {
    [$user, $token] = authUser();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $user->id]);
    LabReport::factory()->processed()->count(2)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/reports-for-verification');

    $response->assertOk();
});
