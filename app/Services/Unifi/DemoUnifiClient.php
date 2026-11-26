<?php

namespace App\Services\Unifi;

use Illuminate\Support\Facades\Log;

/** Simulierte UDM für Demo-Modus und Tests: schaltet nichts frei, merkt sich nur die Aufrufe. */
class DemoUnifiClient implements UnifiClient
{
    public array $calls = [];

    public function authorizeGuest(string $site, string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $quotaMb = null, ?string $apMac = null): void
    {
        $this->calls[] = compact('site', 'mac', 'minutes', 'upKbps', 'downKbps', 'quotaMb', 'apMac');
        Log::info('Demo: Gast würde freigeschaltet', ['site' => $site, 'minutes' => $minutes]);
    }

    public function unauthorizeGuest(string $site, string $mac): void
    {
        $this->calls[] = ['unauthorize' => $mac, 'site' => $site];
    }

    public function sites(): array
    {
        return ['default' => 'Standort München (Demo)', 'nbg' => 'Standort Nürnberg (Demo)', 'aug' => 'Standort Augsburg (Demo)'];
    }

    /** Feste kleine Momentaufnahme für Tests; die Demo-Auswertungen kommen aus DemoData. */
    public function network(string $site): array
    {
        $this->calls[] = ['network' => $site];

        return [
            'devices' => [
                ['id' => 'ap-innen', 'mac' => 'aa:bb:cc:00:00:01', 'name' => 'AP Innen', 'model' => 'U7 Pro', 'online' => true, 'is_ap' => true, 'retries_pct' => 6.0, 'cpu_pct' => 12.0, 'uptime_sec' => 86400],
                ['id' => 'ap-terrasse', 'mac' => 'aa:bb:cc:00:00:02', 'name' => 'AP Terrasse', 'model' => 'U7 Outdoor', 'online' => true, 'is_ap' => true, 'retries_pct' => 21.5, 'cpu_pct' => 9.0, 'uptime_sec' => 86400],
            ],
            'clients' => [
                ['mac' => '11:22:33:44:55:01', 'ap_id' => 'ap-innen', 'wireless' => true, 'guest' => true, 'rx_bytes' => 52428800, 'tx_bytes' => 5242880],
                ['mac' => '11:22:33:44:55:02', 'ap_id' => 'ap-terrasse', 'wireless' => true, 'guest' => true, 'rx_bytes' => null, 'tx_bytes' => null],
                ['mac' => '11:22:33:44:55:03', 'ap_id' => 'ap-innen', 'wireless' => true, 'guest' => false, 'rx_bytes' => null, 'tx_bytes' => null],
            ],
        ];
    }
}
