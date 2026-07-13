<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Mmoollllee\Cms\Concerns\User\BelongsToTenants;
use Mmoollllee\Cms\Contracts\User as UserContract;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants, UserContract
{
    use BelongsToTenants;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;   // tenants()/roles + host-aware Filament tenancy methods

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // NOTE: 'is_superadmin' is deliberately NOT mass-assignable — it is a global
        // authorization kill-switch. Set it explicitly (factory state / seeder), never
        // from request data.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_superadmin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
