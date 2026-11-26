<?php

namespace Tests\Feature;

use App\Services\Unifi\UnifiCloudClient;
use App\Services\Unifi\UnifiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiCloudClientTest extends TestCase
{
    private const BASE = 'https://api.ui.com/v1/connector/consoles/console-1/proxy/network/integration/v1';

    public function test_authorizes_guest_via_cloud_connector(): void
    {
        Http::fake([
            self::BASE.'/sites*' => Http::sequence()
                ->push(['data' => [['id' => 'site-uuid', 'internalReference' => 'default', 'name' => 'Default']]])
                ->push(['data' => [['id' => 'client-uuid', 'macAddress' => 'aa:bb:cc:dd:ee:ff', 'access' => ['type' => 'GUEST', 'authorized' => false]]]])
                ->push(['action' => 'AUTHORIZE_GUEST_ACCESS']),
        ]);

        (new UnifiCloudClient('sm-key', 'console-1'))->authorizeGuest('default', 'AA:BB:CC:DD:EE:FF', 240, 5000, 20000, 1000);

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-API-KEY', 'sm-key') && str_contains($r->url(), '/proxy/network/integration/v1/sites'));
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), "filter=macAddress.eq('aa:bb:cc:dd:ee:ff')"));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/sites/site-uuid/clients/client-uuid/actions'
            && $r['action'] === 'AUTHORIZE_GUEST_ACCESS' && $r['timeLimitMinutes'] === 240
            && $r['rxRateLimitKbps'] === 20000 && $r['txRateLimitKbps'] === 5000 && $r['dataUsageLimitMBytes'] === 1000);
    }

    public function test_unknown_device_gives_clear_error(): void
    {
        Http::fake([self::BASE.'/sites*' => Http::sequence()->push(['data' => [['id' => 's', 'internalReference' => 'default']]])->push(['data' => []])]);
        $this->expectExceptionMessage('nicht im WLAN angemeldet');
        (new UnifiCloudClient('k', 'console-1'))->authorizeGuest('default', 'aa:bb:cc:dd:ee:ff', 60);
    }

    public function test_offline_console(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'DeviceTimeout'], 408)]);
        $this->expectException(UnifiException::class);
        $this->expectExceptionMessage('offline');
        (new UnifiCloudClient('k', 'console-1'))->sites();
    }

    public function test_lists_consoles(): void
    {
        Http::fake(['https://api.ui.com/v1/hosts' => Http::response(['data' => [['id' => 'h1', 'ipAddress' => '1.2.3.4', 'reportedState' => ['name' => 'UDM SE München']]]])]);
        $this->assertSame(['h1' => 'UDM SE München · 1.2.3.4'], (new UnifiCloudClient('k', ''))->consoles());
    }

    public function test_missing_settings(): void
    {
        $this->expectExceptionMessage('fehlt in den Einstellungen');
        (new UnifiCloudClient('', ''))->sites();
    }
}
