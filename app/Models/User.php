<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'password', 'is_agency_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_agency_admin' => 'boolean',
        ];
    }

    public function subAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SubAccount::class)->withPivot('role')->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'agency' => $this->is_agency_admin,
            default => true,
        };
    }

    /** Agency admins can open every sub-account; other users only the ones they were added to. */
    public function getTenants(Panel $panel): Collection
    {
        return $this->is_agency_admin
            ? SubAccount::orderBy('name')->get()
            : $this->subAccounts()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_agency_admin || $this->subAccounts()->whereKey($tenant)->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->getTenants($panel)->first();
    }
}
