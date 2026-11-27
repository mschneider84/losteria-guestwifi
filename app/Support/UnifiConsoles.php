<?php

namespace App\Support;

use App\Services\Unifi\UnifiCloudClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Konsolen (UDM SE je Restaurant) des UI-Kontos – zwischengespeichert für Einstellungen und Standorte. */
class UnifiConsoles
{
    /** @return array<string, array{name:string, ip:?string, owner:bool}> */
    public static function all(): array
    {
        return Cache::get('unifi.hosts', []);
    }

    /** @return array<string, string> id => „Name · IP“ */
    public static function labels(): array
    {
        return collect(self::all())->map(fn ($h) => trim($h['name'].($h['ip'] ? ' · '.$h['ip'] : '').(($h['owner'] ?? true) ? '' : ' · ⚠ nur Admin-Zugriff')))->all();
    }

    public static function ip(?string $id): ?string
    {
        return $id ? (self::all()[$id]['ip'] ?? null) : null;
    }

    public static function isOwner(?string $id): ?bool
    {
        return $id && isset(self::all()[$id]) ? (bool) (self::all()[$id]['owner'] ?? true) : null;
    }

    public static function error(): ?string
    {
        return Cache::get('unifi.hosts.error');
    }

    /** Neu laden; liefert die Fehlermeldung oder null. */
    public static function refresh(): ?string
    {
        try {
            $hosts = UnifiCloudClient::fromSettings()->hosts();
            Cache::put('unifi.hosts', $hosts, 3600);
            Cache::forget('unifi.hosts.error');

            return $hosts ? null : __('Mit diesem Schlüssel ist keine Konsole verbunden. Sind die UDMs im selben UI-Konto (unifi.ui.com)?');
        } catch (Throwable $e) {
            Cache::put('unifi.hosts.error', $e->getMessage(), 300);

            return $e->getMessage();
        }
    }

    public static function forget(): void
    {
        Cache::forget('unifi.hosts');
        Cache::forget('unifi.hosts.error');
    }
}
