<?php

namespace App\Demo;

use App\Models\Location;
use App\Support\Version;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erfundene Vorführdaten. Idempotent: legt nur an, was fehlt, und erneuert
 * die Gast-Anmeldungen einmal je Version und Tag, damit die Statistik aktuell aussieht.
 */
class DemoData
{
    public const LOCATIONS = [
        ['name' => 'Osteria Centrale', 'city' => 'München', 'slug' => 'demo-muenchen', 'unifi_site' => 'default', 'brand_color' => '#db052c', 'weight' => 1.3],
        ['name' => 'Osteria del Ponte', 'city' => 'Nürnberg', 'slug' => 'demo-nuernberg', 'unifi_site' => 'nbg', 'brand_color' => '#1f6b4f', 'weight' => 0.9],
        ['name' => 'Osteria al Porto', 'city' => 'Hamburg', 'slug' => 'demo-hamburg', 'unifi_site' => 'hh', 'brand_color' => '#2a5bb8', 'weight' => 1.0],
    ];

    public static function ensure(): void
    {
        $stamp = Version::version().'-'.now()->toDateString();
        if (Cache::get('demo.stamp') === $stamp && Location::where('is_demo', true)->exists()) {
            return;
        }

        $ids = [];
        foreach (self::LOCATIONS as $l) {
            $loc = Location::firstOrCreate(['slug' => $l['slug']], [
                'name' => $l['name'], 'city' => $l['city'], 'unifi_site' => $l['unifi_site'], 'brand_color' => $l['brand_color'],
                'session_minutes' => 240, 'down_kbps' => 20000, 'up_kbps' => 5000,
                'redirect_url' => 'https://www.example.com/aktionen', 'is_demo' => true,
            ]);
            $ids[$loc->id] = $l['weight'];
        }

        DB::table('guest_sessions')->where('is_demo', true)->delete();
        mt_srand(42);
        $rows = [];
        $devices = [];
        $visits = [];
        $seen = []; // Gerät => Anmeldezeiten (für Aktions-Klicks)
        $types = ['iphone' => 46, 'android' => 38, 'ipad' => 5, 'windows' => 5, 'mac' => 4, 'sonstige' => 2];
        $deviceType = [];
        foreach ($ids as $locId => $weight) {
            $devices[$locId] = [];
            for ($d = 59; $d >= 0; $d--) {
                $day = now()->subDays($d)->startOfDay();
                $base = $day->isWeekend() ? 130 : ($day->isFriday() ? 110 : 75);
                $n = (int) round($base * $weight * (0.8 + mt_rand(0, 40) / 100));
                if ($d === 0) {
                    $n = (int) round($n * min(1, now()->hour / 22));
                }
                for ($i = 0; $i < $n; $i++) {
                    $hour = self::hour();
                    $returning = count($devices[$locId]) > 20 && mt_rand(1, 100) <= 28;
                    $hash = $returning ? $devices[$locId][array_rand($devices[$locId])] : hash('sha256', Str::random(20));
                    if (! $returning) {
                        $devices[$locId][] = $hash;
                    }
                    $at = $day->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
                    if ($at->isFuture()) {
                        continue;
                    }
                    $r = mt_rand(1, 100);
                    $deviceType[$hash] ??= self::pick($types);
                    $seen[$locId][$hash][] = $at->toDateTimeString();
                    if ($d <= 34) {
                        $visits[] = self::visit($locId, $hash, $at, $deviceType[$hash]);
                    }
                    $rows[] = [
                        'location_id' => $locId, 'device_hash' => $hash, 'language' => $r <= 72 ? 'de' : ($r <= 90 ? 'en' : 'it'),
                        'method' => mt_rand(1, 100) <= 14 ? 'email' : 'click', 'is_returning' => $returning, 'status' => 'authorized',
                        'ssid' => 'Osteria-Gast', 'device_type' => $deviceType[$hash], 'authorized_at' => $at, 'expires_at' => $at->copy()->addMinutes(240),
                        'is_demo' => true, 'created_at' => $at, 'updated_at' => $at,
                    ];
                    if (count($rows) >= 500) {
                        DB::table('guest_sessions')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }
        if ($rows) {
            DB::table('guest_sessions')->insert($rows);
        }

        DB::table('client_visits')->where('is_demo', true)->delete();
        foreach (array_chunk($visits, 500) as $chunk) {
            DB::table('client_visits')->insert($chunk);
        }
        self::network($ids);

        if (! DB::table('newsletter_contacts')->where('is_demo', true)->exists()) {
            $first = ['anna', 'luca', 'marie', 'paul', 'giulia', 'felix', 'sofia', 'jonas', 'lea', 'marco', 'emma', 'noah', 'chiara', 'ben', 'mia'];
            $last = ['berger', 'rossi', 'huber', 'bianchi', 'wagner', 'ferrari', 'keller', 'romano', 'schmid', 'conti'];
            $contacts = [];
            for ($i = 0; $i < 240; $i++) {
                $at = now()->subMinutes(mt_rand(30, 60 * 24 * 59));
                $r = mt_rand(1, 100);
                $status = $r <= 78 ? 'confirmed' : ($r <= 92 ? 'pending' : 'unsubscribed');
                $contacts[] = [
                    'location_id' => array_rand($ids), 'email' => $first[array_rand($first)].'.'.$last[array_rand($last)].$i.'@mail.example',
                    'language' => mt_rand(1, 100) <= 80 ? 'de' : 'en', 'token' => Str::random(48), 'status' => $status,
                    'consent_at' => $at, 'consent_text_version' => '2026-11',
                    'confirmed_at' => $status !== 'pending' ? $at->copy()->addMinutes(mt_rand(1, 300)) : null,
                    'unsubscribed_at' => $status === 'unsubscribed' ? $at->copy()->addDays(3) : null,
                    'is_demo' => true, 'created_at' => $at, 'updated_at' => $at,
                ];
            }
            DB::table('newsletter_contacts')->insert($contacts);
        }

        self::promotions(array_keys($ids), $seen);

        Cache::put('demo.stamp', $stamp, 86400);
    }

    /** Zwei erfundene Aktionen, wie sie das Dashboard schicken würde, samt Aufrufen und Klicks. */
    private static function promotions(array $locationIds, array $seen): void
    {
        DB::table('promotion_clicks')->where('is_demo', true)->delete();
        $items = [
            ['external_id' => 'demo-pizza-dezember', 'image' => 'aktion-pizza.jpg', 'priority' => 1, 'placement' => 'both',
                'title' => ['de' => 'Pizza des Monats: Tartufo e Funghi', 'en' => 'Pizza of the month: Tartufo e Funghi', 'it' => 'Pizza del mese: Tartufo e Funghi'],
                'text' => ['de' => 'Mit schwarzem Trüffel und Steinpilzen – nur im Dezember.', 'en' => 'With black truffle and porcini – December only.', 'it' => 'Con tartufo nero e porcini – solo a dicembre.'],
                'cta_label' => ['de' => 'Zur Speisekarte', 'en' => 'See the menu', 'it' => 'Vedi il menù'], 'cta_url' => 'https://www.example.com/speisekarte',
                'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(35)],
            ['external_id' => 'demo-aperitivo', 'image' => 'aktion-aperitivo.jpg', 'priority' => 2, 'placement' => 'online',
                'title' => ['de' => 'Aperitivo: Spritz für 5 €', 'en' => 'Aperitivo: Spritz for €5', 'it' => 'Aperitivo: Spritz a 5 €'],
                'text' => ['de' => 'Montag bis Freitag von 17 bis 19 Uhr an der Bar.', 'en' => 'Monday to Friday, 5 to 7 pm at the bar.', 'it' => 'Dal lunedì al venerdì, dalle 17 alle 19 al bar.'],
                'cta_label' => ['de' => 'Tisch reservieren', 'en' => 'Book a table', 'it' => 'Prenota un tavolo'], 'cta_url' => 'https://www.example.com/reservieren',
                'weekdays' => '1,2,3,4,5', 'time_from' => '17:00', 'time_to' => '19:00', 'starts_at' => now()->subDays(40), 'ends_at' => null],
        ];
        foreach ($items as $item) {
            $image = $item['image'];
            unset($item['image']);
            $path = 'aktionen/'.$image;
            if (! \App\Support\Media::disk()->exists($path)) {
                \App\Support\Media::disk()->put($path, file_get_contents(resource_path('demo/'.$image)));
            }
            $promotion = \App\Models\Promotion::updateOrCreate(['external_id' => $item['external_id'], 'is_demo' => true],
                $item + ['image_path' => $path, 'active' => true, 'is_demo' => true]);

            DB::table('promotion_stats')->where('promotion_id', $promotion->id)->delete();
            $rows = [];
            foreach ($locationIds as $locId) {
                for ($d = 19; $d >= 0; $d--) {
                    $views = mt_rand(40, 110);
                    $rows[] = ['promotion_id' => $promotion->id, 'location_id' => $locId, 'day' => now()->subDays($d)->toDateString(),
                        'views' => $views, 'clicks' => (int) round($views * mt_rand(3, 9) / 100)];
                }
            }
            DB::table('promotion_stats')->insert($rows);

            // Klicks einzelner Geräte; wer klickt, kommt in der Demo etwas häufiger wieder
            $clicks = [];
            foreach ($seen as $locId => $byHash) {
                foreach ($byHash as $hash => $times) {
                    foreach ($times as $i => $t) {
                        if ($t < now()->subDays(20)->toDateTimeString() || mt_rand(1, 100) > 6) {
                            continue;
                        }
                        $comesBack = isset($times[$i + 1]);
                        if ($comesBack || mt_rand(1, 100) <= 45) {
                            $clicks[] = ['promotion_id' => $promotion->id, 'location_id' => $locId, 'device_hash' => $hash,
                                'clicked_at' => \Carbon\Carbon::parse($t)->addMinutes(mt_rand(1, 40)), 'is_demo' => true];
                        }
                        break;
                    }
                }
            }
            foreach (array_chunk($clicks, 500) as $chunk) {
                DB::table('promotion_clicks')->insert($chunk);
            }
        }
    }

    private const AREAS = ['Innen' => 55, 'Terrasse' => 25, 'Bar' => 20];

    /** Ein Besuch zu einer Demo-Anmeldung: Mittags kürzer, abends länger, an der Bar am kürzesten. */
    private static function visit(int $locId, string $hash, \Carbon\Carbon $at, string $type): array
    {
        $area = self::pick(self::AREAS);
        $base = $at->hour < 15 ? 48 : ($at->hour < 18 ? 60 : 98);
        $minutes = (int) max(6, round($base * ($area === 'Bar' ? 0.6 : ($area === 'Terrasse' ? 1.15 : 1)) * (0.55 + mt_rand(0, 90) / 100)));
        $end = $at->copy()->addMinutes($minutes);
        if ($end->isFuture()) {
            $end = now();
            $minutes = max(0, (int) $at->diffInMinutes($end));
        }
        $rx = (int) round($minutes * mt_rand(8, 45) / 10);

        return ['location_id' => $locId, 'device_hash' => $hash, 'area' => $area, 'first_seen_at' => $at, 'last_seen_at' => $end,
            'minutes' => $minutes, 'device_type' => $type, 'rx_mb' => $rx, 'tx_mb' => (int) round($rx * 0.12), 'is_demo' => true];
    }

    /** Access Points und Zählstände alle 15 Minuten (35 Tage bis heute Abend). */
    private static function network(array $ids): void
    {
        DB::table('network_snapshots')->where('is_demo', true)->delete();
        $aps = [
            ['Gastraum vorne', 'Innen', 'U7 Pro'], ['Gastraum hinten', 'Innen', 'U7 Pro'],
            ['Terrasse', 'Terrasse', 'U7 Outdoor'], ['Bar', 'Bar', 'U6+'],
        ];
        $hourCurve = [11 => 0.25, 12 => 0.75, 13 => 0.8, 14 => 0.45, 15 => 0.25, 16 => 0.25, 17 => 0.45, 18 => 0.8, 19 => 1.0, 20 => 0.95, 21 => 0.7, 22 => 0.4, 23 => 0.15];
        $i = 0;
        foreach ($ids as $locId => $weight) {
            $i++;
            $rows = [];
            for ($t = now()->subDays(34)->startOfDay(); $t <= now()->endOfDay(); $t = $t->copy()->addMinutes(15)) {
                $f = $hourCurve[$t->hour] ?? 0;
                $day = $t->isWeekend() ? 1.35 : ($t->isFriday() ? 1.15 : 0.85);
                $guests = (int) round(48 * $weight * $f * $day * (0.85 + mt_rand(0, 30) / 100));
                $terrace = 0.22 + ($t->hour >= 12 && $t->hour <= 17 ? 0.1 : 0) + ($t->isWeekend() ? 0.05 : 0);
                $bar = $t->hour >= 17 ? 0.24 : 0.1;
                $split = ['Terrasse' => (int) round($guests * $terrace), 'Bar' => (int) round($guests * $bar)];
                $split['Innen'] = max(0, $guests - $split['Terrasse'] - $split['Bar']);
                $staff = $f > 0 ? 4 : 1;
                $rows[] = ['location_id' => $locId, 'area' => 'gesamt', 'taken_at' => $t, 'clients' => $guests + $staff, 'guests' => $guests, 'is_demo' => true];
                foreach ($split as $area => $n) {
                    $rows[] = ['location_id' => $locId, 'area' => $area, 'taken_at' => $t, 'clients' => $n + ($area === 'Bar' ? $staff : 0), 'guests' => $n, 'is_demo' => true];
                }
                if (count($rows) >= 800) {
                    DB::table('network_snapshots')->insert($rows);
                    $rows = [];
                }
            }
            if ($rows) {
                DB::table('network_snapshots')->insert($rows);
            }

            $current = DB::table('network_snapshots')->where('location_id', $locId)->where('taken_at', '<=', now())->orderByDesc('taken_at')->limit(4)->get()->keyBy('area');
            foreach ($aps as $k => [$name, $area, $model]) {
                $share = $area === 'Innen' ? 0.5 : 1;
                $clients = (int) round(($current[$area]->clients ?? 0) * $share);
                $weak = $i === 3 && $area === 'Terrasse';
                \App\Models\AccessPoint::updateOrCreate(['location_id' => $locId, 'unifi_id' => "demo-{$locId}-{$k}"], [
                    'mac' => sprintf('02:00:00:00:%02x:%02x', $i, $k), 'name' => $name, 'model' => $model, 'area' => $area, 'state' => 'online',
                    'clients' => $clients, 'guests' => $clients, 'peak_clients' => (int) round(48 * $weight * 1.35 * ($area === 'Innen' ? 0.32 : 0.35)),
                    'retries_pct' => $weak ? 23.4 : round(3 + mt_rand(0, 70) / 10, 1), 'cpu_pct' => mt_rand(8, 30),
                    'uptime_sec' => 86400 * mt_rand(3, 40), 'last_seen_at' => now(), 'is_demo' => true,
                ]);
            }
        }
    }

    private static function pick(array $weights): string
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $k => $w) {
            if (($r -= $w) <= 0) {
                return (string) $k;
            }
        }

        return (string) array_key_first($weights);
    }

    /** Mittags- und Abendspitze wie im Restaurant. */
    private static function hour(): int
    {
        $weights = [10 => 2, 11 => 5, 12 => 13, 13 => 12, 14 => 6, 15 => 3, 16 => 3, 17 => 6, 18 => 11, 19 => 15, 20 => 13, 21 => 7, 22 => 3, 23 => 1];
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $h => $w) {
            if (($r -= $w) <= 0) {
                return $h;
            }
        }

        return 19;
    }
}
