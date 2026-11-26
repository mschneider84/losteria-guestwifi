<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\ApiKey;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Promotion;
use App\Services\Network\NetworkCollector;
use App\Services\Unifi\DemoUnifiClient;
use App\Services\Unifi\UnifiClient;
use App\Services\Unifi\UnifiCloudClient;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(UnifiClient::class, new DemoUnifiClient);
        $this->location = Location::create(['name' => 'Osteria Test', 'slug' => 'muenchen', 'unifi_site' => 'default']);
    }

    public function test_collector_stores_areas_and_pseudonymous_visits(): void
    {
        $collector = app(NetworkCollector::class);
        $collector->run();

        $this->assertSame(2, AccessPoint::count());
        AccessPoint::where('unifi_id', 'ap-terrasse')->update(['area' => 'Terrasse']);
        AccessPoint::where('unifi_id', 'ap-innen')->update(['area' => 'Innen']);

        $this->travel(5)->minutes();
        $collector->run();

        $last = DB::table('network_snapshots')->where('taken_at', DB::table('network_snapshots')->max('taken_at'))->pluck('guests', 'area');
        $this->assertSame(2, (int) $last['gesamt']);
        $this->assertSame(1, (int) $last['Innen']);
        $this->assertSame(1, (int) $last['Terrasse']);
        $this->assertSame(3, (int) DB::table('network_snapshots')->where('area', 'gesamt')->orderByDesc('id')->value('clients'));

        // Zwei Gastgeräte, ein Besuch je Gerät, fortgeschrieben statt neu angelegt
        $this->assertSame(2, DB::table('client_visits')->count());
        $this->assertSame(5, (int) DB::table('client_visits')->max('minutes'));
        $this->assertSame(50, (int) DB::table('client_visits')->max('rx_mb'));

        // Keine MAC im Klartext
        $dump = json_encode(DB::table('client_visits')->get()).json_encode(DB::table('network_snapshots')->get());
        $this->assertStringNotContainsString('11:22:33', $dump);
        $this->assertSame(GuestSession::hashDevice('11:22:33:44:55:01'), DB::table('client_visits')->orderBy('id')->value('device_hash'));

        // Schwacher Access Point wird erkannt
        $this->assertNotEmpty(AccessPoint::where('unifi_id', 'ap-terrasse')->first()->problems());
    }

    public function test_new_visit_after_gap(): void
    {
        $collector = app(NetworkCollector::class);
        $collector->run();
        $this->travel(NetworkCollector::VISIT_GAP_MINUTES + 5)->minutes();
        $collector->run();
        $this->assertSame(4, DB::table('client_visits')->count());
    }

    public function test_cloud_client_reads_devices_and_clients_defensively(): void
    {
        $base = 'https://api.ui.com/v1/connector/consoles/c1/proxy/network/integration/v1';
        Http::fake([
            $base.'/sites?*' => Http::response(['data' => [['id' => 'site-1', 'internalReference' => 'default']]]),
            $base.'/sites/site-1/devices?*' => Http::response(['offset' => 0, 'limit' => 200, 'count' => 2, 'totalCount' => 2, 'data' => [
                ['id' => 'd1', 'macAddress' => 'AA:00:00:00:00:01', 'name' => 'AP Bar', 'model' => 'U7 Pro', 'state' => 'ONLINE', 'features' => ['accessPoint']],
                ['id' => 'd2', 'macAddress' => 'AA:00:00:00:00:02', 'name' => 'Switch', 'model' => 'USW', 'state' => 'ONLINE', 'features' => ['switching']],
            ]]),
            $base.'/sites/site-1/devices/d1/statistics/latest' => Http::response(['uptimeSec' => 100, 'cpuUtilizationPct' => 12.5, 'interfaces' => ['radios' => [['frequencyGHz' => 2.4, 'txRetriesPct' => 18.2], ['frequencyGHz' => 5, 'txRetriesPct' => 4]]]]),
            $base.'/sites/site-1/clients?*' => Http::response(['offset' => 0, 'count' => 2, 'totalCount' => 2, 'data' => [
                ['id' => 'c1', 'type' => 'WIRELESS', 'macAddress' => 'BB:00:00:00:00:01', 'uplinkDeviceId' => 'd1', 'access' => ['type' => 'GUEST']],
                ['id' => 'c2', 'type' => 'WIRED', 'macAddress' => 'BB:00:00:00:00:02', 'access' => ['type' => 'DEFAULT'], 'rxBytes' => 1048576],
            ]]),
        ]);

        $net = (new UnifiCloudClient('k', 'c1'))->network('default');

        $this->assertTrue($net['devices'][0]['is_ap']);
        $this->assertFalse($net['devices'][1]['is_ap']);
        $this->assertSame(18.2, $net['devices'][0]['retries_pct']);
        $this->assertSame(12.5, $net['devices'][0]['cpu_pct']);
        $this->assertSame(['mac' => 'bb:00:00:00:00:01', 'ap_id' => 'd1', 'wireless' => true, 'guest' => true, 'rx_bytes' => null, 'tx_bytes' => null], $net['clients'][0]);
        $this->assertSame(1048576, $net['clients'][1]['rx_bytes']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/api/s/'));
    }

    public function test_api_returns_occupancy_and_analysis(): void
    {
        app(NetworkCollector::class)->run();
        AccessPoint::query()->update(['area' => 'Innen']);
        $this->travel(5)->minutes();
        app(NetworkCollector::class)->run();
        [, $key] = ApiKey::generate('Dashboard', false);
        $this->getJson('/api/v1/auslastung')->assertUnauthorized();

        $this->withToken($key)->getJson('/api/v1/auslastung')->assertOk()
            ->assertJsonPath('daten.0.standort.slug', 'muenchen')
            ->assertJsonPath('daten.0.gaeste', 2)
            ->assertJsonPath('daten.0.bereiche.0.bereich', 'Innen');

        $this->withToken($key)->getJson('/api/v1/netzwerk?standort=muenchen')->assertOk()
            ->assertJsonStructure(['zeitraum', 'standorte' => [['bereiche', 'auslastung' => ['heatmap', 'spitzenzeiten'], 'verweildauer', 'stammgaeste', 'aktionen_wirkung', 'technik' => ['geraetetypen', 'access_points']]]]);

        $this->withToken($key)->getJson('/api/v1/netzwerk?von=2026-01-01&bis=2026-12-31')->assertStatus(422);
    }

    public function test_promotion_click_is_stored_pseudonymously_and_device_type_recorded(): void
    {
        $promotion = Promotion::create(['external_id' => 'p1', 'title' => ['de' => 'Aktion'], 'cta_url' => 'https://example.com', 'placement' => 'both', 'active' => true]);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
            ->get('/guest/s/default/?id=aa:bb:cc:dd:ee:01&ap=aa:00:00:00:00:01&url=https://example.com');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
            ->post(route('portal.connect', $this->location->slug), ['terms' => '1']);
        $this->assertSame('iphone', GuestSession::sole()->device_type);

        $this->get(route('portal.promotion', [$this->location->slug, $promotion]))->assertRedirect('https://example.com');
        $this->assertSame(GuestSession::hashDevice('aa:bb:cc:dd:ee:01'), DB::table('promotion_clicks')->value('device_hash'));
    }

    public function test_purge_removes_old_visits_and_snapshots(): void
    {
        Settings::set('privacy.visit_days', 30);
        DB::table('client_visits')->insert(['location_id' => $this->location->id, 'device_hash' => str_repeat('a', 64), 'first_seen_at' => now()->subDays(40), 'last_seen_at' => now()->subDays(40), 'minutes' => 60]);
        DB::table('network_snapshots')->insert(['location_id' => $this->location->id, 'area' => 'gesamt', 'taken_at' => now()->subDays(500), 'clients' => 1, 'guests' => 1]);
        $this->artisan('portal:purge')->assertSuccessful();
        $this->assertSame(0, DB::table('client_visits')->count());
        $this->assertSame(0, DB::table('network_snapshots')->count());
    }

    public function test_network_job_can_be_switched_off(): void
    {
        Settings::set('network.enabled', false);
        $this->artisan('portal:network')->expectsOutputToContain('ausgeschaltet')->assertSuccessful();
        $this->assertSame(0, DB::table('network_snapshots')->count());
    }
}
