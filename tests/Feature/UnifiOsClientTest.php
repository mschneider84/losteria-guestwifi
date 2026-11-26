<?php

namespace Tests\Feature;

use App\Services\Unifi\UnifiException;
use App\Services\Unifi\UnifiOsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiOsClientTest extends TestCase
{
    private function client(): UnifiOsClient
    {
        return new UnifiOsClient('https://udm.test', 'portal', 'geheim');
    }

    public function test_login_then_authorize_guest_with_csrf_token(): void
    {
        Http::fake([
            'udm.test/api/auth/login' => Http::response(['unique_id' => 'x'], 200, ['Set-Cookie' => 'TOKEN=abc123; Path=/', 'X-CSRF-Token' => 'csrf-1']),
            'udm.test/proxy/network/api/s/default/cmd/stamgr' => Http::response(['meta' => ['rc' => 'ok'], 'data' => []]),
        ]);

        $this->client()->authorizeGuest('default', 'AA:BB:CC:DD:EE:FF', 240, 5000, 20000, null, '11:22:33:44:55:66');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/auth/login') && $r['username'] === 'portal');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/cmd/stamgr')
            && $r->hasHeader('X-CSRF-Token', 'csrf-1')
            && $r['cmd'] === 'authorize-guest' && $r['mac'] === 'aa:bb:cc:dd:ee:ff'
            && $r['minutes'] === 240 && $r['down'] === 20000 && $r['up'] === 5000 && ! isset($r['bytes']));
    }

    public function test_unifi_error_is_reported(): void
    {
        Http::fake([
            'udm.test/api/auth/login' => Http::response([], 200, ['Set-Cookie' => 'TOKEN=abc; Path=/', 'X-CSRF-Token' => 'c']),
            'udm.test/proxy/*' => Http::response(['meta' => ['rc' => 'error', 'msg' => 'api.err.UnknownStation']]),
        ]);
        $this->expectException(UnifiException::class);
        $this->client()->authorizeGuest('default', 'aa:bb:cc:dd:ee:ff', 60);
    }

    public function test_wrong_password(): void
    {
        Http::fake(['udm.test/api/auth/login' => Http::response(['code' => 'AUTHENTICATION_FAILED_INVALID_CREDENTIALS'], 401)]);
        $this->expectExceptionMessage('Anmeldung an der UDM fehlgeschlagen');
        $this->client()->sites();
    }
}
