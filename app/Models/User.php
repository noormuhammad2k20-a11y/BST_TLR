<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN  = 'admin';
    public const ROLE_STAFF  = 'staff';
    public const ROLE_TAILOR = 'tailor';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_TAILOR];

    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        'role',
        'title',
        'phone',
        'badge',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'tailor_id');
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // Cloth Store Advanced Roles & Permissions
    public function csRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ClothStore\Role::class, 'cs_user_roles', 'user_id', 'role_id');
    }

    public function hasCsPermission($permissionName): bool
    {
        foreach ($this->csRoles as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Scopes & helpers                                                    */
    /* ------------------------------------------------------------------ */

    public function scopeTailors(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_TAILOR)->where('is_active', true);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->map(fn ($p) => mb_substr($p, 0, 1))
            ->take(2)
            ->implode('');

        return mb_strtoupper($initials ?: 'U');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN  => 'Administrator',
            self::ROLE_TAILOR => 'Tailor',
            default           => 'Staff',
        };
    }

    public function getShortNameAttribute(): string
    {
        return $this->display_name ?: (explode(' ', trim((string) $this->name))[0] ?? $this->name);
    }
}
