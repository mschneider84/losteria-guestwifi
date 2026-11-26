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
        ['name' => 'Osteria Centrale', 'city' => 'München', 'slug' => 'demo-muenchen', 'unifi_site' => 'default', 'brand_color' => '#b3261e', 'weight' => 1.3],
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
                    $rows[] = [
                        'location_id' => $locId, 'device_hash' => $hash, 'language' => $r <= 72 ? 'de' : ($r <= 90 ? 'en' : 'it'),
                        'method' => mt_rand(1, 100) <= 14 ? 'email' : 'click', 'is_returning' => $returning, 'status' => 'authorized',
                        'ssid' => 'Osteria-Gast', 'authorized_at' => $at, 'expires_at' => $at->copy()->addMinutes(240),
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

        Cache::put('demo.stamp', $stamp, 86400);
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
