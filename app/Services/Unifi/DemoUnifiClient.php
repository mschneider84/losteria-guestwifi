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
}
