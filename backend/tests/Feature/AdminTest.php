<?php

/*
|--------------------------------------------------------------------------
| Clinex — Admin API Tests
|--------------------------------------------------------------------------
| Tests for admin dashboard, user management, template CRUD, report
| oversight, system health, and permission enforcement.
*/

use App\Models\User;
use App\Models\LabReport;
use App\Models\ReportBatch;
use App\Models\ReportTemplate;
use App\Models\Patient;

function adminAuth(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $token = $admin->createToken('test')->plainTextToken;
    return [$admin, $token];
}

function techAuth(): array
{
    $tech = User::factory()->create([
        'role' => 'lab_technician',
        'permissions' => [],
    ]);
    $token = $tech->createToken('test')->plainTextToken;
    return [$tech, $token];
}

function techWithPermission(string $permission): array
{
    $tech = User::factory()->create([
        'role' => 'lab_technician',
        'permissions' => [$permission => true],
    ]);
    $token = $tech->createToken('test')->plainTextToken;
    return [$tech, $token];
}

// ─── Admin Dashboard ─────────────────────────────────────────────────

it('returns admin dashboard stats for admin', function () {
    [$admin, $token] = adminAuth();

    // Seed some data
    User::factory()->count(3)->create();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $admin->id]);
    LabReport::factory()->count(5)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $admin->id,
    ]);
    Patient::factory()->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'total_users',
            'total_reports',
            'total_patients',
            'total_batches',
            'verified_reports',
            'unverified_reports',
            'processing_reports',
            'failed_reports',
            'users_by_role',
            'reports_this_week',
            'reports_today',
        ]);
});

it('denies admin dashboard to tech without permission', function () {
    [$tech, $token] = techAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/dashboard');

    $response->assertStatus(403);
});

it('allows admin dashboard for tech with view_analytics', function () {
    [$tech, $token] = techWithPermission('view_analytics');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/dashboard');

    $response->assertOk();
});

// ─── User Management ─────────────────────────────────────────────────

it('lists users for admin', function () {
    [$admin, $token] = adminAuth();
    User::factory()->count(5)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('filters users by role', function () {
    [$admin, $token] = adminAuth();
    User::factory()->count(3)->create(['role' => 'lab_technician']);
    User::factory()->count(2)->create(['role' => 'admin']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users?role=lab_technician');

    $response->assertOk();
});

it('searches users by name and email', function () {
    [$admin, $token] = adminAuth();
    User::factory()->create(['name' => 'Chivorn Test', 'email' => 'chivorn@test.com']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users?search=Chivorn');

    $response->assertOk();
});

it('updates user role', function () {
    [$admin, $token] = adminAuth();
    $user = User::factory()->create(['role' => 'lab_technician']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/users/{$user->id}/role", [
            'role' => 'admin',
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'User role updated successfully.']);

    $user->refresh();
    expect($user->role)->toBe('admin');
});

it('prevents admin from demoting themselves', function () {
    [$admin, $token] = adminAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/users/{$admin->id}/role", [
            'role' => 'lab_technician',
        ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'You cannot change your own role.']);
});

it('updates user permissions', function () {
    [$admin, $token] = adminAuth();
    $tech = User::factory()->create(['role' => 'lab_technician']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/users/{$tech->id}/permissions", [
            'permissions' => [
                'view_analytics' => true,
                'export_data' => true,
            ],
        ]);

    $response->assertOk();

    $tech->refresh();
    expect($tech->hasPermission('view_analytics'))->toBeTrue();
    expect($tech->hasPermission('export_data'))->toBeTrue();
});

it('rejects invalid permission keys', function () {
    [$admin, $token] = adminAuth();
    $tech = User::factory()->create(['role' => 'lab_technician']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/users/{$tech->id}/permissions", [
            'permissions' => ['hack_system' => true],
        ]);

    $response->assertStatus(422);
});

it('prevents setting explicit permissions on admin user', function () {
    [$admin, $token] = adminAuth();
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/admin/users/{$otherAdmin->id}/permissions", [
            'permissions' => ['view_analytics' => true],
        ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Admin users have all permissions by default.']);
});

it('deletes a user', function () {
    [$admin, $token] = adminAuth();
    $user = User::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/users/{$user->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents self-deletion', function () {
    [$admin, $token] = adminAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/users/{$admin->id}");

    $response->assertStatus(403)
        ->assertJson(['message' => 'You cannot delete your own account.']);
});

it('denies user management to tech without permission', function () {
    [$tech, $token] = techAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users');

    $response->assertStatus(403);
});

// ─── Template Management ─────────────────────────────────────────────

it('lists templates for admin', function () {
    [$admin, $token] = adminAuth();
    ReportTemplate::factory()->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/templates');

    $response->assertOk();
});

it('creates a new template', function () {
    [$admin, $token] = adminAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/templates', [
            'name' => 'KVH Hospital Template',
            'hospital_code' => 'KVH',
            'schema' => ['panels' => ['hematology', 'biochemistry']],
        ]);

    $response->assertStatus(201)
        ->assertJson(['message' => 'Template created successfully.']);

    $this->assertDatabaseHas('report_templates', [
        'name' => 'KVH Hospital Template',
        'hospital_code' => 'KVH',
    ]);
});

it('rejects duplicate hospital_code', function () {
    [$admin, $token] = adminAuth();
    ReportTemplate::factory()->create(['hospital_code' => 'KVH']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/templates', [
            'name' => 'Another Template',
            'hospital_code' => 'KVH',
            'schema' => ['panels' => ['hematology']],
        ]);

    $response->assertStatus(422);
});

it('toggles template active status', function () {
    [$admin, $token] = adminAuth();
    $template = ReportTemplate::factory()->create(['is_active' => false]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/templates/{$template->id}/toggle");

    $response->assertOk()
        ->assertJson(['is_active' => true]);

    $template->refresh();
    expect($template->is_active)->toBeTrue();
});

it('deletes a template', function () {
    [$admin, $token] = adminAuth();
    $template = ReportTemplate::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/templates/{$template->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('report_templates', ['id' => $template->id]);
});

// ─── Report Oversight ────────────────────────────────────────────────

it('lists reports with filters for admin', function () {
    [$admin, $token] = adminAuth();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $admin->id]);
    LabReport::factory()->count(5)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $admin->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/reports');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('bulk deletes reports', function () {
    [$admin, $token] = adminAuth();
    $batch = ReportBatch::factory()->create(['uploaded_by' => $admin->id]);
    $reports = LabReport::factory()->count(3)->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $admin->id,
    ]);

    $ids = $reports->pluck('id')->toArray();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/reports/bulk-delete', [
            'report_ids' => $ids,
        ]);

    $response->assertOk()
        ->assertJson(['deleted_count' => 3]);

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('lab_reports', ['id' => $id]);
    }
});

// ─── System Health ───────────────────────────────────────────────────

it('returns system health for admin', function () {
    [$admin, $token] = adminAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/system-health');

    $response->assertOk()
        ->assertJsonStructure([
            'database' => ['status'],
            'ai_engines' => ['status', 'engines', 'enabled_count'],
            'queue' => ['pending_jobs', 'failed_jobs'],
            'disk',
        ]);
});

it('flushes failed jobs', function () {
    [$admin, $token] = adminAuth();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/admin/flush-failed-jobs');

    $response->assertOk()
        ->assertJson(['success' => true]);
});

it('resets stale processing jobs', function () {
    [$admin, $token] = adminAuth();

    // Create a stale processing report
    $batch = ReportBatch::factory()->create(['uploaded_by' => $admin->id]);
    $report = LabReport::factory()->create([
        'batch_id' => $batch->id,
        'uploaded_by' => $admin->id,
        'status' => 'processing',
        'updated_at' => now()->subHours(3),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/reset-stale-jobs');

    $response->assertOk()
        ->assertJson(['success' => true]);

    $report->refresh();
    expect($report->status)->toBe('failed');
});
