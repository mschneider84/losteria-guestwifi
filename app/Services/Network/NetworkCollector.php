<?php

namespace App\Services\Network;

use App\Models\AccessPoint;
use App\Models\GuestSession;
use App\Models\Location;
use App\Services\Unifi\UnifiManager;
use App\Support\ErrorCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Holt alle 5 Minuten eine Momentaufnahme je Standort über die UniFi-Cloud und verdichtet sie:
 * Zählstände je Bereich (ohne Personenbezug) und Besuche von Gastgeräten (nur HMAC, nie die MAC).
 */
class NetworkCollector
{
    /** Ein Gerät gilt als neuer Besuch, wenn es länger als so viele Minuten weg war. */
    public const VISIT_GAP_MINUTES = 20;

    public function __construct(private readonly UnifiManager $unifi) {}

    /** @return array<string, string> Ergebnis je Standort */
    public function run(): array
    {
        $result = [];
        foreach (Location::where('is_demo', false)->where('active', true)->get() as $location) {
            try {
                $n = $this->collect($location);
                $result[$location->slug] = "{$n['clients']} Geräte, davon {$n['guests']} Gäste";
                Cache::forget("network.failed.{$location->id}");
            } catch (Throwable $e) {
                $result[$location->slug] = 'Fehler: '.$e->getMessage();
                // Nur den ersten Fehler einer Serie protokollieren, nicht alle 5 Minuten
                if (! Cache::get("network.failed.{$location->id}")) {
                    DB::table('system_logs')->insert([
                        'level' => 'warning', 'code' => ErrorCode::make(),
                        'message' => Str::limit("Netzwerk-Auswertung {$location->name}: ".$e->getMessage(), 490),
                        'context' => json_encode(['location' => $location->slug]), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                Cache::put("network.failed.{$location->id}", true, 86400);
            }
        }
        Cache::put('network.last_run', ['at' => now()->toDateTimeString(), 'result' => $result], 86400 * 7);

        return $result;
    }

    /** @return array{clients:int, guests:int} */
    public function collect(Location $location, ?array $net = null): array
    {
        $net ??= $this->unifi->client(false)->network($location->unifi_site);
        $now = now();

        // Gastgeräte: Gast-Netz laut UniFi oder in den letzten 24 h über das Portal freigeschaltet
        $portalGuests = GuestSession::where('location_id', $location->id)->where('status', 'authorized')
            ->where('authorized_at', '>=', $now->copy()->subDay())->pluck('device_type', 'device_hash');
        $clients = collect($net['clients'])->map(function ($c) use ($portalGuests) {
            $c['hash'] = GuestSession::hashDevice($c['mac']);
            unset($c['mac']); // MAC ab hier nicht mehr verwenden
            $c['guest'] = $c['guest'] || $portalGuests->has($c['hash']);

            return $c;
        });

        // Access Points aktualisieren
        $aps = collect($net['devices'])->where('is_ap', true);
        foreach ($aps as $d) {
            $here = $clients->where('ap_id', $d['id']);
            $ap = AccessPoint::firstOrNew(['location_id' => $location->id, 'unifi_id' => $d['id']]);
            $ap->fill([
                'mac' => $d['mac'], 'name' => $d['name'] ?: ($ap->name ?: 'Access Point'), 'model' => $d['model'],
                'state' => $d['online'] ? 'online' : 'offline', 'clients' => $here->count(), 'guests' => $here->where('guest', true)->count(),
                'retries_pct' => $d['retries_pct'], 'cpu_pct' => $d['cpu_pct'], 'uptime_sec' => $d['uptime_sec'],
                'peak_clients' => max((int) $ap->peak_clients, $here->count()), 'is_demo' => false,
            ]);
            if ($d['online']) {
                $ap->last_seen_at = $now;
            }
            $ap->save();
        }
        $areaOf = AccessPoint::where('location_id', $location->id)->pluck('area', 'unifi_id');
        $area = fn ($apId) => ($apId && ($areaOf[$apId] ?? null)) ? $areaOf[$apId] : null;

        // Zählstände je Bereich
        $rows = [['area' => 'gesamt', 'clients' => $clients->count(), 'guests' => $clients->where('guest', true)->count()]];
        foreach ($areaOf->filter()->unique() as $name) {
            $in = $clients->filter(fn ($c) => $area($c['ap_id']) === $name);
            $rows[] = ['area' => $name, 'clients' => $in->count(), 'guests' => $in->where('guest', true)->count()];
        }
        DB::table('network_snapshots')->insert(array_map(fn ($r) => $r + ['location_id' => $location->id, 'taken_at' => $now, 'is_demo' => false], $rows));

        // Besuche der Gastgeräte fortschreiben
        $guests = $clients->where('guest', true)->keyBy('hash');
        $open = DB::table('client_visits')->where('location_id', $location->id)->where('is_demo', false)
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(self::VISIT_GAP_MINUTES))
            ->whereIn('device_hash', $guests->keys())->orderBy('id')->get()->keyBy('device_hash');
        $new = [];
        foreach ($guests as $hash => $c) {
            $mb = fn ($b) => $b === null ? null : (int) round($b / 1048576);
            if ($v = $open[$hash] ?? null) {
                DB::table('client_visits')->where('id', $v->id)->update([
                    'last_seen_at' => $now,
                    'minutes' => min(65535, (int) round(\Carbon\Carbon::parse($v->first_seen_at)->diffInMinutes($now))),
                    'area' => $v->area ?? $area($c['ap_id']),
                    'rx_mb' => $c['rx_bytes'] === null ? $v->rx_mb : max((int) $v->rx_mb, $mb($c['rx_bytes'])),
                    'tx_mb' => $c['tx_bytes'] === null ? $v->tx_mb : max((int) $v->tx_mb, $mb($c['tx_bytes'])),
                ]);
            } else {
                $new[] = [
                    'location_id' => $location->id, 'device_hash' => $hash, 'area' => $area($c['ap_id']),
                    'first_seen_at' => $now, 'last_seen_at' => $now, 'minutes' => 0,
                    'device_type' => $portalGuests[$hash] ?? null, 'rx_mb' => $mb($c['rx_bytes']), 'tx_mb' => $mb($c['tx_bytes']), 'is_demo' => false,
                ];
            }
        }
        foreach (array_chunk($new, 500) as $chunk) {
            DB::table('client_visits')->insert($chunk);
        }

        return ['clients' => $clients->count(), 'guests' => $guests->count()];
    }
}
