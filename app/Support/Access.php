<?php

namespace App\Support;

use Filament\Facades\Filament;

class Access
{
    /** Whether the signed-in user is an admin of the current sub-account. */
    public static function isTenantAdmin(): bool
    {
        return (bool) auth()->user()?->isAdminOf(Filament::getTenant());
    }

    public static function tenantId(): ?int
    {
        return Filament::getTenant()?->getKey();
    }
}
