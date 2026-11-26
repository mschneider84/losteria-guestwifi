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

        self::promotions(array_keys($ids));

        Cache::put('demo.stamp', $stamp, 86400);
    }

    /** Zwei erfundene Aktionen, wie sie das Dashboard schicken würde, samt Aufrufen und Klicks. */
    private static function promotions(array $locationIds): void
    {
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
        }
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
