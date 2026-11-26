<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app()->setLocale(in_array($user->locale, ['de', 'en'], true) ? $user->locale : 'de');
        }

        return $next($request);
    }
}
