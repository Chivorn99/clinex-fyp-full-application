<?php

/*
|--------------------------------------------------------------------------
| Clinex — Authentication API Tests
|--------------------------------------------------------------------------
| Tests for login, register, logout, and token validation endpoints.
*/

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// ─── Login ───────────────────────────────────────────────────────────

it('logs in with valid credentials and returns token', function () {
    $user = User::factory()->create([
        'email' => 'tech@clinex.test',
        'password' => bcrypt('password123'),
        'role' => 'lab_technician',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'tech@clinex.test',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'role'],
            'access_token',
            'token_type',
        ])
        ->assertJson(['token_type' => 'Bearer']);
});

it('rejects login with wrong password', function () {
    User::factory()->create([
        'email' => 'tech@clinex.test',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'tech@clinex.test',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid login details']);
});

it('rejects login with non-existent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@clinex.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
});

it('validates email format on login', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('requires email and password for login', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

// ─── Registration ────────────────────────────────────────────────────

it('registers a new user successfully', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'New Technician',
        'email' => 'new@clinex.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'lab_technician',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'role'],
            'access_token',
            'token_type',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'new@clinex.test',
        'role' => 'lab_technician',
    ]);
});

it('rejects registration with duplicate email', function () {
    User::factory()->create(['email' => 'existing@clinex.test']);

    $response = $this->postJson('/api/register', [
        'name' => 'Duplicate User',
        'email' => 'existing@clinex.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'lab_technician',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects registration with mismatched passwords', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'New User',
        'email' => 'new2@clinex.test',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword!',
        'role' => 'lab_technician',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects registration with invalid role', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Bad Role',
        'email' => 'badrole@clinex.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'superadmin',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

it('accepts admin role during registration', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Admin User',
        'email' => 'admin@clinex.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'admin',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', [
        'email' => 'admin@clinex.test',
        'role' => 'admin',
    ]);
});

// ─── Logout ──────────────────────────────────────────────────────────

it('logs out an authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out']);

    // Token should be revoked
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects logout without authentication', function () {
    $response = $this->postJson('/api/logout');
    $response->assertStatus(401);
});

// ─── Token / Auth Guard ──────────────────────────────────────────────

it('returns current user with valid token', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'role' => 'admin',
    ]);
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user');

    $response->assertOk()
        ->assertJson([
            'name' => 'Test User',
            'role' => 'admin',
        ]);
});

it('rejects access to protected routes without token', function () {
    $response = $this->getJson('/api/user');
    $response->assertStatus(401);
});

it('rejects access with invalid token', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token-here')
        ->getJson('/api/user');

    $response->assertStatus(401);
});
