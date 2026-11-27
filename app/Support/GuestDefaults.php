<?php

namespace App\Support;

use App\Models\Location;

/**
 * Standardwerte der Gästeseite für alle Restaurants: Texte (drei Sprachen) und Freischaltung (Dauer, Bandbreite, Datenvolumen).
 * Reihenfolge bei Texten: Text des Standorts → Standardtext aus „Gästeseite & Rechtstexte“ → eingebauter Text (lang/…/portal.php).
 */
class GuestDefaults
{
    /** Feld => [Beschriftung in der Verwaltung, Höchstlänge] */
    public const TEXTS = [
        'headline' => ['Überschrift', 120],
        'welcome' => ['Begrüßung', 300],
        'connect' => ['Text auf dem Knopf', 40],
        'done_title' => ['Überschrift nach dem Verbinden', 120],
        'done_text' => ['Text nach dem Verbinden', 300],
    ];

    /** Feld => [Einstellung, Standardwert] – null bzw. 0 heißt unbegrenzt */
    public const LIMITS = [
        'session_minutes' => ['guest.session_minutes', 240],
        'down_kbps' => ['guest.down_kbps', 20000],
        'up_kbps' => ['guest.up_kbps', 5000],
        'quota_mb' => ['guest.quota_mb', null],
    ];

    public static function text(string $field, string $lang): ?string
    {
        return (Legal::all()['ui.'.$field][$lang] ?? null) ?: null;
    }

    /** @return array<string, array<string, string>> Feld => [Sprache => Text] */
    public static function texts(): array
    {
        $all = Legal::all();

        return collect(self::TEXTS)->map(fn ($x, $f) => $all['ui.'.$f] ?? [])->all();
    }

    public static function saveText(string $field, array $content, ?int $userId): void
    {
        Legal::save('ui.'.$field, $content, $userId);
    }

    /** @return array{session_minutes:int, down_kbps:?int, up_kbps:?int, quota_mb:?int} */
    public static function limits(): array
    {
        $out = [];
        foreach (self::LIMITS as $field => [$key, $default]) {
            $v = Settings::has($key) ? (int) Settings::get($key) : $default;
            $out[$field] = $field === 'session_minutes' ? (int) $v : ($v ?: null);
        }

        return $out;
    }

    public static function saveLimits(array $values): void
    {
        foreach (self::LIMITS as $field => [$key]) {
            Settings::set($key, (string) (int) ($values[$field] ?? 0));
        }
    }

    /** Standardwerte auf alle Standorte übertragen, die „Standardwerte verwenden“ angehakt haben. */
    public static function applyToLocations(): int
    {
        return Location::where('use_default_limits', true)->update(self::limits());
    }
}
