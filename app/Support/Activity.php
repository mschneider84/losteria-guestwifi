<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Activity
{
    /** Diese Felder landen nie im Protokoll. */
    public const HIDDEN = ['password', 'unifi.password', 'email', 'token'];

    public static function log(string $action, ?string $subject = null, array $details = []): void
    {
        if (session('demo')) {
            return;
        }
        foreach (self::HIDDEN as $key) {
            if (array_key_exists($key, $details)) {
                $details[$key] = '***';
            }
        }
        DB::table('activity_log')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject' => $subject,
            'details' => $details ? json_encode($details) : null,
            'ip_hash' => request()?->ip() ? hash('sha256', request()->ip().config('app.key')) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
