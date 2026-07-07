<?php

/*
|--------------------------------------------------------------------------
| Clinex — Unit Tests: User Model
|--------------------------------------------------------------------------
| Tests for RBAC permission logic, role checks, and model behavior.
*/

use App\Models\User;

it('identifies admin users', function () {
    $admin = User::factory()->make(['role' => 'admin']);
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isLabTechnician())->toBeFalse();
});

it('identifies lab technician users', function () {
    $tech = User::factory()->make(['role' => 'lab_technician']);
    expect($tech->isLabTechnician())->toBeTrue();
    expect($tech->isAdmin())->toBeFalse();
});

it('gives admin all permissions automatically', function () {
    $admin = User::factory()->make(['role' => 'admin', 'permissions' => []]);

    expect($admin->hasPermission('manage_users'))->toBeTrue();
    expect($admin->hasPermission('manage_templates'))->toBeTrue();
    expect($admin->hasPermission('manage_reports'))->toBeTrue();
    expect($admin->hasPermission('view_analytics'))->toBeTrue();
    expect($admin->hasPermission('view_system_health'))->toBeTrue();
    expect($admin->hasPermission('export_data'))->toBeTrue();
});

it('checks individual permissions for lab technician', function () {
    $tech = User::factory()->make([
        'role' => 'lab_technician',
        'permissions' => [
            'view_analytics' => true,
            'export_data' => true,
            'manage_users' => false,
        ],
    ]);

    expect($tech->hasPermission('view_analytics'))->toBeTrue();
    expect($tech->hasPermission('export_data'))->toBeTrue();
    expect($tech->hasPermission('manage_users'))->toBeFalse();
    expect($tech->hasPermission('manage_templates'))->toBeFalse();
});

it('returns false for unset permissions', function () {
    $tech = User::factory()->make([
        'role' => 'lab_technician',
        'permissions' => null,
    ]);

    expect($tech->hasPermission('manage_users'))->toBeFalse();
});

it('checks hasAnyPermission correctly', function () {
    $tech = User::factory()->make([
        'role' => 'lab_technician',
        'permissions' => ['view_analytics' => true],
    ]);

    expect($tech->hasAnyPermission(['manage_users', 'view_analytics']))->toBeTrue();
    expect($tech->hasAnyPermission(['manage_users', 'manage_templates']))->toBeFalse();
});

it('lists all granted permissions for admin', function () {
    $admin = User::factory()->make(['role' => 'admin']);
    $granted = $admin->getGrantedPermissions();

    expect($granted)->toEqual(User::AVAILABLE_PERMISSIONS);
});

it('lists granted permissions for tech', function () {
    $tech = User::factory()->make([
        'role' => 'lab_technician',
        'permissions' => [
            'view_analytics' => true,
            'export_data' => true,
            'manage_users' => false,
        ],
    ]);

    $granted = $tech->getGrantedPermissions();
    expect($granted)->toContain('view_analytics');
    expect($granted)->toContain('export_data');
    expect($granted)->not->toContain('manage_users');
});

it('defines all 6 available permissions', function () {
    expect(User::AVAILABLE_PERMISSIONS)->toHaveCount(6);
    expect(User::AVAILABLE_PERMISSIONS)->toContain('manage_users');
    expect(User::AVAILABLE_PERMISSIONS)->toContain('manage_templates');
    expect(User::AVAILABLE_PERMISSIONS)->toContain('manage_reports');
    expect(User::AVAILABLE_PERMISSIONS)->toContain('view_analytics');
    expect(User::AVAILABLE_PERMISSIONS)->toContain('view_system_health');
    expect(User::AVAILABLE_PERMISSIONS)->toContain('export_data');
});

it('hashes password automatically', function () {
    $user = User::factory()->create(['password' => 'plaintext123']);
    expect($user->password)->not->toBe('plaintext123');
});
