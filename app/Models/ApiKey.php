<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A sub-account API key. Only a SHA-256 hash is stored; the plain key is shown once. */
class ApiKey extends Model
{
    use BelongsToSubAccount;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return array{0: ApiKey, 1: string} the key and its plain-text token */
    public static function issue(SubAccount $account, string $name): array
    {
        $plain = 'le_'.Str::random(40);

        $key = static::create([
            'sub_account_id' => $account->id,
            'name' => $name,
            'prefix' => substr($plain, 0, 10),
            'token_hash' => hash('sha256', $plain),
        ]);

        return [$key, $plain];
    }

    public static function findByToken(?string $plain): ?self
    {
        return $plain ? static::where('token_hash', hash('sha256', $plain))->first() : null;
    }
}
