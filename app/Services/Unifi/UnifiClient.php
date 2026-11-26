<?php

namespace App\Services\Unifi;

interface UnifiClient
{
    /**
     * Gast an der UDM freischalten.
     *
     * @param  string  $site  UniFi-Site-Name (z. B. "default")
     * @param  string  $mac  MAC des Gastgeräts (aus dem Parameter "id" der Weiterleitung)
     */
    public function authorizeGuest(string $site, string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $quotaMb = null, ?string $apMac = null): void;

    public function unauthorizeGuest(string $site, string $mac): void;

    /** Verbindung prüfen; liefert die Sites der UDM als [name => Beschreibung]. */
    public function sites(): array;
}
