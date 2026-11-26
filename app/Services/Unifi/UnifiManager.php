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

    public function demo(): DemoUnifiClient
    {
        return $this->demo ??= new DemoUnifiClient;
    }
}
