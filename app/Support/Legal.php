<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rechtstexte der Gästeseite. Reihenfolge: im Portal gepflegter Text → Link aus den Einstellungen → (nur Nutzungsbedingungen) eingebauter Kurztext.
 */
class Legal
{
    /** Schlüssel => [Adresse auf der Gästeseite, Übersetzungsschlüssel, Einstellung für externen Link] */
    public const PAGES = [
        'terms' => ['nutzungsbedingungen', 'portal.terms', 'portal.terms_url'],
        'privacy' => ['datenschutz', 'portal.privacy', 'portal.privacy_url'],
        'imprint' => ['impressum', 'portal.imprint', 'portal.imprint_url'],
    ];

    public const LABELS = ['terms' => 'Nutzungsbedingungen', 'privacy' => 'Datenschutzerklärung', 'imprint' => 'Impressum'];

    /** @return array<string, array<string, string>> key => [lang => Text] */
    public static function all(): array
    {
        return Cache::remember('legal.texts', 3600, fn () => DB::table('legal_texts')->get()
            ->mapWithKeys(fn ($r) => [$r->key => (array) json_decode($r->content, true)])->all());
    }

    public static function text(string $key, string $lang): ?string
    {
        $texts = self::all()[$key] ?? [];

        return ($texts[$lang] ?? null) ?: (($texts['de'] ?? null) ?: null);
    }

    public static function html(string $key, string $lang): ?string
    {
        $text = self::text($key, $lang);

        return $text === null ? null : Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** Link zur Seite oder null, wenn es weder Text noch Link gibt. */
    public static function url(string $key, Location $location, string $lang): ?string
    {
        if (self::text($key, $lang)) {
            return route('portal.legal', [$location->slug, self::PAGES[$key][0], 'lang' => $lang]);
        }

        return Settings::get(self::PAGES[$key][2]) ?: null;
    }

    /** @return array<string, array{label:string, url:string}> */
    public static function links(Location $location, string $lang): array
    {
        $links = [];
        foreach (['imprint', 'privacy', 'terms'] as $key) {
            if ($url = self::url($key, $location, $lang)) {
                $links[$key] = ['label' => __(self::PAGES[$key][1]), 'url' => $url];
            }
        }

        return $links;
    }

    public static function keyFromSlug(string $slug): ?string
    {
        foreach (self::PAGES as $key => [$s]) {
            if ($s === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function save(string $key, array $content, ?int $userId): void
    {
        $content = array_intersect_key(array_map(fn ($t) => trim((string) $t), $content), array_flip(['de', 'en', 'it']));
        DB::table('legal_texts')->updateOrInsert(['key' => $key], [
            'content' => json_encode(array_filter($content), JSON_UNESCAPED_UNICODE),
            'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now(),
        ]);
        Cache::forget('legal.texts');
    }
}
