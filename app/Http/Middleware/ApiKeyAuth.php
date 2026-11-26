<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schlüssel nur im Header (Authorization: Bearer … oder X-Api-Key), nie in der URL.
 * 120 Anfragen/Minute je Schlüssel, 20 Fehlversuche je IP in 10 Minuten.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $ipKey = 'api-fail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            return response()->json(['error' => 'Zu viele Fehlversuche. Bitte später erneut versuchen.'], 429);
        }

        $plain = $request->bearerToken() ?: $request->header('X-Api-Key');
        $key = $plain ? ApiKey::findByPlain($plain) : null;
        if (! $key) {
            RateLimiter::hit($ipKey, 600);

            return response()->json(['error' => 'Ungültiger oder abgelaufener Schlüssel.'], 401);
        }
        if ($ability === 'write' && ! $key->can_write) {
            return response()->json(['error' => 'Dieser Schlüssel darf nur lesen.'], 403);
        }
        if (RateLimiter::tooManyAttempts('api-key:'.$key->id, 120)) {
            return response()->json(['error' => 'Zu viele Anfragen.'], 429);
        }
        RateLimiter::hit('api-key:'.$key->id, 60);

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now(), 'last_used_ip_hash' => hash('sha256', $request->ip().config('app.key'))])->saveQuietly();
        }
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
