<?php

/*
|--------------------------------------------------------------------------
| Clinex — Feature Tests: Middleware (CheckPermission)
|--------------------------------------------------------------------------
| Tests that the permission middleware correctly enforces access control.
*/

use App\Models\User;

it('allows admin access to all permission-protected routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = $admin->createToken('test')->plainTextToken;

    // Admin should access all protected admin routes
    $routes = [
        '/api/admin/dashboard',
        '/api/admin/users',
        '/api/admin/system-health',
    ];

    foreach ($routes as $route) {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson($route);

        expect($response->status())->not->toBe(403, "Admin should not get 403 on {$route}");
    }
});

it('blocks lab technician without specific permission', function () {
    $tech = User::factory()->create([
        'role' => 'lab_technician',
        'permissions' => [],
    ]);
    $token = $tech->createToken('test')->plainTextToken;

    // manage_users required
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/users');

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You do not have permission to perform this action.',
            'required_permission' => 'manage_users',
        ]);
});

it('allows lab technician with specific permission', function () {
    $tech = User::factory()->create([
        'role' => 'lab_technician',
        'permissions' => ['view_system_health' => true],
    ]);
    $token = $tech->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/system-health');

    $response->assertOk();
});

it('rejects unauthenticated requests with 401', function () {
    $response = $this->getJson('/api/admin/dashboard');
    $response->assertStatus(401);
});
