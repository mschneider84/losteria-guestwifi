<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Location;
use App\Models\User;
use App\Services\Unifi\UnifiCloudClient;
use App\Support\Settings;
use App\Support\UnifiConsoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Eine UDM SE je Restaurant: Konsole (und optional Schlüssel) je Standort. */
class MultiConsoleTest extends TestCase
{
    use RefreshDatabase;

    private Location $woergl;

    private Location $kufstein;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::set('unifi.cloud_api_key', 'konto-key');
        $this->woergl = Location::create(['name' => 'Wörgl', 'slug' => 'woergl', 'unifi_site' => 'default', 'unifi_console_id' => 'c-woergl', 'unifi_wan_ip' => '80.122.69.70']);
        $this->kufstein = Location::create(['name' => 'Kufstein', 'slug' => 'kufstein', 'unifi_site' => 'default', 'unifi_console_id' => 'c-kuf', 'unifi_api_key' => 'eigener-key']);
    }

    public function test_each_location_uses_its_own_console_and_key(): void
    {
        Http::fake([
            'api.ui.com/v1/connector/consoles/*/proxy/network/integration/v1/sites?*' => Http::response(['data' => [['id' => 's1', 'internalReference' => 'default']]]),
            'api.ui.com/v1/connector/consoles/*/proxy/network/integration/v1/sites/s1/clients?*' => Http::response(['data' => [['id' => 'cl1', 'macAddress' => 'aa:bb:cc:dd:ee:01'], ['id' => 'cl2', 'macAddress' => 'AA:BB:CC:DD:EE:02']]]),
            'api.ui.com/*' => Http::response([]),
        ]);
        UnifiCloudClient::forLocation($this->woergl)->authorizeGuest('default', 'aa:bb:cc:dd:ee:01', 60);
        UnifiCloudClient::forLocation($this->kufstein)->authorizeGuest('default', 'aa:bb:cc:dd:ee:02', 60);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/consoles/c-woergl/') && $r->hasHeader('X-API-KEY', 'konto-key'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/consoles/c-kuf/') && $r->hasHeader('X-API-KEY', 'eigener-key'));
    }

    public function test_client_lookup_falls_back_to_list_and_never_takes_a_wrong_device(): void
    {
        UnifiCloudClient::$lookupWaitMs = 0;
        $base = 'api.ui.com/v1/connector/consoles/c-woergl/proxy/network/integration/v1';
        Http::fake(function (Request $r) use ($base) {
            if (str_contains($r->url(), $base.'/sites?')) {
                return Http::response(['data' => [['id' => 's1', 'internalReference' => 'default']]]);
            }
            if (str_contains($r->url(), '/sites/s1/clients?') && str_contains(urldecode($r->url()), 'filter=')) {
                return Http::response(['data' => [['id' => 'fremd', 'macAddress' => '11:22:33:44:55:66']]]); // Filter ignoriert
            }
            if (str_contains($r->url(), '/sites/s1/clients?')) {
                return Http::response(['offset' => 0, 'totalCount' => 2, 'data' => [
                    ['id' => 'fremd', 'macAddress' => '11:22:33:44:55:66'], ['id' => 'gast', 'macAddress' => 'AA-BB-CC-DD-EE-01'],
                ]]);
            }

            return Http::response([]);
        });
        UnifiCloudClient::forLocation($this->woergl)->authorizeGuest('default', 'aa:bb:cc:dd:ee:01', 60);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/clients/gast/actions'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/clients/fremd/'));

        try {
            UnifiCloudClient::forLocation($this->woergl)->authorizeGuest('default', 'aa:bb:cc:dd:ee:99', 60);
            $this->fail('kein Fehler');
        } catch (\App\Services\Unifi\UnifiException $e) {
            $this->assertStringContainsString('2 Geräte auf der Site', $e->getMessage());
            $this->assertStringNotContainsString('aa:bb:cc:dd:ee:99', $e->getMessage());
        }
    }

    public function test_location_key_is_stored_encrypted(): void
    {
        $raw = DB::table('locations')->where('id', $this->kufstein->id)->value('unifi_api_key');
        $this->assertNotSame('eigener-key', $raw);
        $this->assertSame('eigener-key', $this->kufstein->fresh()->unifi_api_key);
    }

    public function test_guest_page_finds_restaurant_by_access_point_ip_or_choice(): void
    {
        AccessPoint::create(['location_id' => $this->kufstein->id, 'unifi_id' => 'ap1', 'mac' => 'aa:00:00:00:00:09']);

        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:01&ap=AA:00:00:00:00:09')->assertRedirect(route('portal.show', ['location' => 'kufstein']));
        $this->withServerVariables(['REMOTE_ADDR' => '80.122.69.70'])
            ->get('/guest/s/default/?id=aa:bb:cc:dd:ee:01')->assertRedirect(route('portal.show', ['location' => 'woergl']));
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->get('/guest/s/default/?id=aa:bb:cc:dd:ee:01')->assertOk()->assertSee('Wörgl')->assertSee('Kufstein')->assertSee('standort=kufstein', false);
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:01&standort=kufstein')->assertRedirect(route('portal.show', ['location' => 'kufstein']));
    }

    public function test_consoles_show_ownership_and_location_form_offers_them(): void
    {
        Http::fake(['https://api.ui.com/v1/hosts' => Http::response(['data' => [
            ['id' => 'c-woergl', 'ipAddress' => '80.122.69.70', 'owner' => true, 'reportedState' => ['name' => 'AT_WOERGL_UDM_01']],
            ['id' => 'c-kuf', 'ipAddress' => '1.2.3.4', 'owner' => false, 'reportedState' => ['name' => 'AT_KUFSTEIN_UDM_01']],
        ]])]);
        $this->assertNull(UnifiConsoles::refresh());
        $this->assertStringContainsString('nur Admin', UnifiConsoles::labels()['c-kuf']);

        $admin = User::create(['name' => 'A', 'email' => 'a@test.example', 'password' => 'passwort-lang-genug', 'role' => 'superadmin']);
        $this->actingAs($admin)->get(route('admin.locations.edit', $this->woergl))->assertOk()->assertSee('AT_WOERGL_UDM_01 · 80.122.69.70');

        $this->put(route('admin.locations.update', $this->kufstein), [
            'name' => 'Kufstein', 'slug' => 'kufstein', 'unifi_site' => 'default', 'unifi_console_id' => 'c-woergl',
            'brand_color' => '#b3261e', 'session_minutes' => 240,
        ])->assertRedirect();
        $k = $this->kufstein->fresh();
        $this->assertSame('c-woergl', $k->unifi_console_id);
        $this->assertSame('80.122.69.70', $k->unifi_wan_ip);
        $this->assertSame('eigener-key', $k->unifi_api_key); // leer gelassen = unverändert
    }

    public function test_403_explains_owner_requirement(): void
    {
        Http::fake(['api.ui.com/*' => Http::response(['message' => 'insufficient permissions for this host'], 403)]);
        $this->expectExceptionMessage('UniFi-Anwendungen');
        UnifiCloudClient::forLocation($this->woergl)->sites();
    }

    public function test_status_page_shows_unifi_per_location(): void
    {
        \Illuminate\Support\Facades\Cache::put("unifi.health.{$this->woergl->id}", ['ok' => false, 'at' => now()->toDateTimeString(), 'error' => 'HTTP 403'], 60);
        $admin = User::create(['name' => 'A', 'email' => 'a@test.example', 'password' => 'passwort-lang-genug', 'role' => 'superadmin']);
        $this->actingAs($admin)->get(route('admin.status'))->assertOk()
            ->assertSee('UniFi · Wörgl')->assertSee('HTTP 403')
            ->assertSee('UniFi · Kufstein')->assertSee('noch nicht geprüft')
            ->assertDontSee('Zugang fehlt in den Einstellungen');
    }
}
