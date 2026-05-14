<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
     use HasFactory, Notifiable, HasApiTokens;

    /**
     * All available permission flags for the RBAC system.
     */
    public const AVAILABLE_PERMISSIONS = [
        'manage_users',
        'manage_templates',
        'manage_reports',
        'view_analytics',
        'view_system_health',
        'export_data',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'profile_pic',
        'phone_number',
        'specialization',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLabTechnician(): bool
    {
        return $this->role === 'lab_technician';
    }

    /**
     * Check if the user has a specific permission.
     * Admin role automatically has ALL permissions.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return !empty($permissions[$permission]);
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the list of granted permissions for this user.
     */
    public function getGrantedPermissions(): array
    {
        if ($this->isAdmin()) {
            return self::AVAILABLE_PERMISSIONS;
        }

        $permissions = $this->permissions ?? [];
        return array_keys(array_filter($permissions));
    }
}
