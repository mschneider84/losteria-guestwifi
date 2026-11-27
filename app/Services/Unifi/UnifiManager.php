<?php

namespace App\Services\Unifi;

/** Liefert die echte UDM-Anbindung oder im Demo-Modus die Simulation. */
class UnifiManager
{
    private ?DemoUnifiClient $demo = null;

    public function client(bool $demo = false): UnifiClient
    {
        if ($demo) {
            return $this->demo ??= new DemoUnifiClient;
        }

        return app(UnifiClient::class);
    }

    /** Anbindung für einen Standort: im Cloud-Modus dessen eigene Konsole (UDM SE je Restaurant). */
    public function forLocation(\App\Models\Location $location, bool $demo = false): UnifiClient
    {
        if ($demo) {
            return $this->demo();
        }
        // Fest gebundene Instanz (z. B. in Tests) hat Vorrang; im Betrieb ist UnifiClient nicht geteilt gebunden
        if (app()->isShared(UnifiClient::class)) {
            return app(UnifiClient::class);
        }

        return \App\Support\Settings::get('unifi.mode') === 'local'
            ? app(UnifiClient::class)
            : UnifiCloudClient::forLocation($location);
    }

    public function demo(): DemoUnifiClient
    {
        return $this->demo ??= new DemoUnifiClient;
    }
}
