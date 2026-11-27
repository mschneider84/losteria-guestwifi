<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Einstellungen in der Datenbank statt in der .env.
 * Geheimnisse werden verschlüsselt gespeichert und nie wieder angezeigt (write-only).
 */
class Settings
{
    /** Schlüssel => [Gruppe, Bezeichnung, Typ, Standard, geheim] */
    public const FIELDS = [
        'unifi.mode' => ['unifi', 'Verbindung zur UDM', 'select:cloud=Über die UniFi-Cloud (empfohlen),local=Direkt (VPN/Freigabe nötig)', 'cloud', false],
        'unifi.cloud_api_key' => ['unifi', 'Site Manager API-Schlüssel (unifi.ui.com → API) – gilt für alle Standorte', 'password', '', true],
        'unifi.console_id' => ['unifi', 'Standard-Konsole (nur wenn am Standort keine gewählt ist)', 'text', '', false],
        'unifi.url' => ['unifi', 'Nur bei „Direkt“: Adresse der UDM SE', 'url', 'https://192.168.1.1', false],
        'unifi.username' => ['unifi', 'Nur bei „Direkt“: Benutzer (lokales Service-Konto)', 'text', '', false],
        'unifi.password' => ['unifi', 'Nur bei „Direkt“: Passwort', 'password', '', true],
        'unifi.verify_tls' => ['unifi', 'Nur bei „Direkt“: Zertifikat prüfen', 'bool', false, false],
        'unifi.timeout' => ['unifi', 'Zeitlimit je Aufruf (Sekunden)', 'int', 8, false],
        'network.enabled' => ['network', 'Netzwerk-Auswertungen alle 5 Minuten aus der UniFi-Cloud abholen', 'bool', true, false],
        'mail.host' => ['mail', 'SMTP-Server', 'text', '', false],
        'mail.port' => ['mail', 'SMTP-Port', 'int', 587, false],
        'mail.username' => ['mail', 'SMTP-Benutzer', 'text', '', false],
        'mail.password' => ['mail', 'SMTP-Passwort', 'password', '', true],
        'mail.from_address' => ['mail', 'Absender-Adresse', 'email', '', false],
        'mail.from_name' => ['mail', 'Absender-Name', 'text', '', false],
        'privacy.session_days' => ['privacy', 'Gast-Anmeldungen löschen nach (Tagen)', 'int', 90, false],
        'privacy.pending_days' => ['privacy', 'Unbestätigte Newsletter-Anmeldungen löschen nach (Tagen)', 'int', 14, false],
        'privacy.visit_days' => ['privacy', 'Besuche (Verweildauer, Wiederkehr) löschen nach (Tagen)', 'int', 90, false],
        'privacy.snapshot_days' => ['privacy', 'Auslastungs-Zählstände (ohne Personenbezug) löschen nach (Tagen)', 'int', 400, false],
        'privacy.consent_version' => ['privacy', 'Version des Einwilligungstextes', 'text', '2026-11', false],
        'modules.newsletter' => ['portal', 'Newsletter-Modul (Anmeldung auf der Gästeseite, Kontakte, Bestätigungs-Mails)', 'bool', false, false],
        'portal.terms_url' => ['portal', 'Externer Link Nutzungsbedingungen & Datenschutz (nur wenn unter „Gästeseite & Rechtstexte“ kein Text steht)', 'url', '', false],
        'portal.imprint_url' => ['portal', 'Externer Link Impressum (optional)', 'url', '', false],
        'brand.name' => ['brand', 'Name', 'text', "L'Osteria Gäste-WLAN", false],
        'brand.company' => ['brand', 'Firma (Copyright)', 'text', "L'Osteria", false],
        'brand.color' => ['brand', 'Markenfarbe', 'color', '#db052c', false],
    ];

    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return self::cast($key, $all[$key]);
        }

        return $default ?? (self::FIELDS[$key][3] ?? null);
    }

    public static function has(string $key): bool
    {
        $all = self::all();

        return isset($all[$key]) && $all[$key] !== '';
    }

    public static function set(string $key, mixed $value): void
    {
        $secret = self::FIELDS[$key][4] ?? false;
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        $stored = ($secret && $value !== null && $value !== '') ? Crypt::encryptString((string) $value) : $value;

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $stored, 'encrypted' => $secret, 'updated_at' => now(), 'created_at' => now()]
        );
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
        Cache::forget('settings.all');
    }

    /** Alle Werte (entschlüsselt) als Array – im Cache nur Arrays, nie Objekte. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            if (! Schema::hasTable('settings')) {
                return self::$cache = [];
            }
            $rows = Cache::remember('settings.all', 300, fn () => DB::table('settings')->get(['key', 'value', 'encrypted'])
                ->map(fn ($r) => (array) $r)->all());
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $value = $row['value'];
            if ($row['encrypted'] && $value) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (Throwable) {
                    $value = null;
                }
            }
            $out[$row['key']] = $value;
        }

        return self::$cache = $out;
    }

    private static function cast(string $key, mixed $value): mixed
    {
        return match (self::FIELDS[$key][2] ?? 'text') {
            'bool' => in_array($value, [true, 1, '1', 'true', 'on'], true),
            'int' => (int) $value,
            default => $value,
        };
    }

    /** Beim Start: Einstellungen überschreiben config(). */
    public static function applyToConfig(): void
    {
        if (self::has('mail.from_address')) {
            config(['mail.from.address' => self::get('mail.from_address')]);
        }
        if (self::has('mail.from_name')) {
            config(['mail.from.name' => self::get('mail.from_name')]);
        }
        if (self::has('mail.host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => self::get('mail.host'),
                'mail.mailers.smtp.port' => self::get('mail.port'),
                'mail.mailers.smtp.username' => self::get('mail.username'),
                'mail.mailers.smtp.password' => self::get('mail.password'),
            ]);
        }
        config(['app.name' => self::get('brand.name')]);
    }
}
