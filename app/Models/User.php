<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nik',
        'position',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function locationAssignments(): HasMany
    {
        return $this->hasMany(LocationAssignment::class, 'user_id');
    }

    public function activeLocationAssignment(): HasOne
    {
        return $this->hasOne(LocationAssignment::class, 'user_id')
            ->where('is_active', true)
            ->with('location');
    }

    protected function activeLocation(): Attribute
    {
        return Attribute::get(fn () => $this->activeLocationAssignment?->location);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, [UserRole::OWNER, UserRole::ADMIN], true);
    }
}
