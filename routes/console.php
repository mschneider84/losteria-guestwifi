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
    $visits = DB::table('client_visits')->where('is_demo', false)
        ->where('last_seen_at', '<', now()->subDays(Settings::get('privacy.visit_days')))->delete();
    DB::table('promotion_clicks')->where('is_demo', false)
        ->where('clicked_at', '<', now()->subDays(Settings::get('privacy.visit_days')))->delete();
    $snapshots = DB::table('network_snapshots')->where('is_demo', false)
        ->where('taken_at', '<', now()->subDays(Settings::get('privacy.snapshot_days')))->delete();
    Cache::put('privacy.last_purge', ['at' => now()->toDateTimeString(), 'sessions' => $sessions, 'pending' => $pending, 'unsubscribed' => $unsub, 'visits' => $visits, 'snapshots' => $snapshots], 86400 * 7);
    $this->info("Gelöscht: {$sessions} Anmeldungen, {$pending} unbestätigt, {$unsub} abgemeldet, {$visits} Besuche, {$snapshots} Zählstände");
})->purpose('Löschfristen anwenden');
Schedule::command('portal:purge')->dailyAt('03:30')->timezone('Europe/Berlin');

Artisan::command('portal:demo', function () {
    Cache::forget('demo.stamp');
    DemoData::ensure();
    $this->info('Demo-Daten erneuert.');
})->purpose('Demo-Daten anlegen bzw. erneuern');

// Netzwerk-Auswertungen: alle 5 Minuten Momentaufnahme je Standort über die UniFi-Cloud
Artisan::command('portal:network', function (\App\Services\Network\NetworkCollector $collector) {
    if (! Settings::get('network.enabled')) {
        $this->info('Netzwerk-Auswertungen sind in den Einstellungen ausgeschaltet.');

        return;
    }
    foreach ($collector->run() as $slug => $line) {
        $this->line("{$slug}: {$line}");
    }
})->purpose('Netzwerk-Momentaufnahme abholen (Bereiche, Auslastung, Besuche)');
Schedule::command('portal:network')->everyFiveMinutes()->withoutOverlapping(10);
