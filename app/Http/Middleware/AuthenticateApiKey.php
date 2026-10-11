<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer-token auth for the public REST API. Every request is scoped to the key's sub-account. */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = ApiKey::findByToken($request->bearerToken() ?? $request->header('X-Api-Key'));

        if (! $key || ! $key->subAccount?->is_active) {
            return response()->json(['message' => 'Invalid or missing API key. Send it as "Authorization: Bearer <key>".'], 401);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('subAccount', $key->subAccount);

        return $next($request);
    }
}
