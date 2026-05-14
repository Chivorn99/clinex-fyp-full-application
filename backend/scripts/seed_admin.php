<?php

use App\Models\User;

// Ensure an admin user exists with known password
$user = User::where('email', 'admin@clinex.com')->first();

if (!$user) {
    $user = User::create([
        'name' => 'ClineX Admin',
        'email' => 'admin@clinex.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    echo "Created admin user (ID: {$user->id})\n";
} else {
    $user->update([
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    echo "Updated admin user (ID: {$user->id})\n";
}

// Also ensure the test user has a known password
$testUser = User::where('email', 'test@example.com')->first();
if ($testUser) {
    $testUser->update(['password' => bcrypt('password')]);
    echo "Updated test user password (ID: {$testUser->id})\n";
}
