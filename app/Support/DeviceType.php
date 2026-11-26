<?php

namespace App\Support;

/** Grobe Geräteart aus dem User-Agent der Gästeseite – ohne Modell, Version oder Kennung. */
class DeviceType
{
    public const LABELS = ['iphone' => 'iPhone', 'ipad' => 'iPad', 'android' => 'Android', 'windows' => 'Windows', 'mac' => 'Mac', 'sonstige' => 'Sonstige'];

    public static function fromUserAgent(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        $ua = strtolower($ua);

        return match (true) {
            str_contains($ua, 'iphone') => 'iphone',
            str_contains($ua, 'ipad') => 'ipad',
            str_contains($ua, 'android') => 'android',
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'macintosh') || str_contains($ua, 'mac os x') => 'mac',
            default => 'sonstige',
        };
    }

    public static function label(?string $type): string
    {
        return self::LABELS[$type] ?? 'Unbekannt';
    }
}
