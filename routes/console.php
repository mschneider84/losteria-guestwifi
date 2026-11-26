<?php
use Illuminate\Support\Facades\Artisan;

use App\Demo\DemoData;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Heartbeat für den Systemstatus
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->toDateTimeString(), 86400))->everyMinute()->name('heartbeat');

// Löschfristen (DSGVO): alte Gast-Anmeldungen und unbestätigte Newsletter-Anmeldungen entfernen
Artisan::command('portal:purge', function () {
    $sessions = DB::table('guest_sessions')->where('is_demo', false)
        ->where('created_at', '<', now()->subDays(Settings::get('privacy.session_days')))->delete();
    $pending = DB::table('newsletter_contacts')->where('is_demo', false)->where('status', 'pending')
        ->where('created_at', '<', now()->subDays(Settings::get('privacy.pending_days')))->delete();
    $unsub = DB::table('newsletter_contacts')->where('is_demo', false)->where('status', 'unsubscribed')
        ->where('unsubscribed_at', '<', now()->subDays(30))->delete();
    Cache::put('privacy.last_purge', ['at' => now()->toDateTimeString(), 'sessions' => $sessions, 'pending' => $pending, 'unsubscribed' => $unsub], 86400 * 7);
    $this->info("Gelöscht: {$sessions} Anmeldungen, {$pending} unbestätigt, {$unsub} abgemeldet");
})->purpose('Löschfristen anwenden');
Schedule::command('portal:purge')->dailyAt('03:30')->timezone('Europe/Berlin');

Artisan::command('portal:demo', function () {
    Cache::forget('demo.stamp');
    DemoData::ensure();
    $this->info('Demo-Daten erneuert.');
})->purpose('Demo-Daten anlegen bzw. erneuern');
