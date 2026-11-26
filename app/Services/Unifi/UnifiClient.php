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

    /**
     * Momentaufnahme des Netzwerks einer Site für die Auswertungen.
     *
     * @return array{devices: list<array{id:string, mac:?string, name:?string, model:?string, online:bool, is_ap:bool, retries_pct:?float, cpu_pct:?float, uptime_sec:?int}>,
     *               clients: list<array{mac:string, ap_id:?string, wireless:bool, guest:bool, rx_bytes:?int, tx_bytes:?int}>}
     */
    public function network(string $site): array;

    /** Verbindung prüfen; liefert die Sites der UDM als [name => Beschreibung]. */
    public function sites(): array;
}
