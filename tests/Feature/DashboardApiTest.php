<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\Promotion;
use App\Services\Unifi\DemoUnifiClient;
use App\Services\Unifi\UnifiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private string $write;

    private string $read;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->app->instance(UnifiClient::class, new DemoUnifiClient);
        [, $this->write] = ApiKey::generate('Dashboard', true);
        [, $this->read] = ApiKey::generate('Nur lesen', false);
        Location::create(['name' => 'Osteria Test', 'slug' => 'muenchen', 'unifi_site' => 'default']);
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_requires_key_in_header(): void
    {
        $this->getJson('/api/v1/status')->assertUnauthorized();
        $this->getJson('/api/v1/status?key='.$this->read)->assertUnauthorized();
        $this->getJson('/api/v1/status', ['Authorization' => 'Bearer '.$this->read])->assertOk()->assertJsonPath('standorte', 1);
        $this->getJson('/api/v1/status', ['X-Api-Key' => $this->read])->assertOk();
    }

    public function test_expired_key_is_rejected(): void
    {
        [, $plain] = ApiKey::generate('alt', false, now()->subDay());
        $this->getJson('/api/v1/status', ['X-Api-Key' => $plain])->assertUnauthorized();
    }

    public function test_stats_per_location(): void
    {
        $loc = Location::first();
        GuestSession::create(['location_id' => $loc->id, 'device_hash' => 'a', 'status' => 'authorized', 'language' => 'it']);
        GuestSession::create(['location_id' => $loc->id, 'device_hash' => 'a', 'status' => 'authorized', 'is_returning' => true]);
        GuestSession::create(['location_id' => $loc->id, 'device_hash' => 'b', 'status' => 'failed']);

        $res = $this->getJson('/api/v1/auswertung?standort=muenchen', ['X-Api-Key' => $this->read])->assertOk();
        $res->assertJsonPath('gesamt.anmeldungen', 2)
            ->assertJsonPath('standorte.0.geraete', 1)
            ->assertJsonPath('standorte.0.wiederkehrer_anmeldungen', 1)
            ->assertJsonPath('standorte.0.sprachen.it', 1)
            ->assertJsonPath('standorte.0.pro_tag.'.now()->toDateString(), 2);
        $this->getJson('/api/v1/auswertung?von=2026-01-10&bis=2026-01-01', ['X-Api-Key' => $this->read])->assertUnprocessable();
    }

    public function test_read_key_cannot_write(): void
    {
        $this->putJson('/api/v1/aktionen/x', ['titel' => ['de' => 'Test']], ['X-Api-Key' => $this->read])->assertForbidden();
    }

    public function test_dashboard_pushes_promotion_and_guest_sees_it(): void
    {
        Http::fake(['dashboard.example/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $this->putJson('/api/v1/aktionen/aktion-42', [
            'titel' => ['de' => 'Pizza des Monats', 'it' => 'Pizza del mese'],
            'text' => ['de' => 'Nur im Dezember'],
            'button_text' => ['de' => 'Zur Karte'], 'button_url' => 'https://www.example.com/karte',
            'bild_url' => 'https://dashboard.example/bilder/pizza.png',
            'standorte' => ['muenchen'], 'platzierung' => 'both',
        ], ['X-Api-Key' => $this->write])->assertCreated()->assertJsonPath('daten.id', 'aktion-42');

        $promotion = Promotion::sole();
        Storage::disk('public')->assertExists($promotion->image_path);

        // Gästeseite zeigt die Aktion, Bild kommt vom Portal selbst
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->get('/wlan/muenchen?lang=it')->assertSee('Pizza del mese')->assertSee(route('media.promotion', $promotion->id), false);
        $this->get(route('media.promotion', $promotion))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->post('/wlan/muenchen/verbinden', ['terms' => '1']);
        $this->get('/wlan/muenchen/online')->assertSee('Zur Karte');
        $this->get(route('portal.promotion', ['muenchen', $promotion]))->assertRedirect('https://www.example.com/karte');

        $stats = $this->getJson('/api/v1/auswertung', ['X-Api-Key' => $this->read])->json('standorte.0.aktionen.0');
        $this->assertSame(['id' => 'aktion-42', 'aufrufe' => 2, 'klicks' => 1], $stats);

        // Update ohne Bild entfernt es, Löschen räumt auf
        $this->putJson('/api/v1/aktionen/aktion-42', ['titel' => ['de' => 'Neu']], ['X-Api-Key' => $this->write])->assertOk();
        Storage::disk('public')->assertMissing($promotion->image_path);
        $this->deleteJson('/api/v1/aktionen/aktion-42', [], ['X-Api-Key' => $this->write])->assertNoContent();
        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_promotion_respects_location_time_and_weekday(): void
    {
        $loc = Location::first();
        $p = Promotion::create(['external_id' => 'a', 'title' => ['de' => 'Aperitivo'], 'weekdays' => '1,2,3,4,5', 'time_from' => '17:00', 'time_to' => '19:00']);
        $monday = now()->startOfWeek()->setTime(18, 0);
        $this->assertTrue($p->appliesTo($loc, $monday));
        $this->assertFalse($p->appliesTo($loc, $monday->copy()->setTime(20, 0)));
        $this->assertFalse($p->appliesTo($loc, $monday->copy()->addDays(5)));
        $p->update(['location_slugs' => ['andere']]);
        $this->assertFalse($p->fresh()->appliesTo($loc, $monday));
    }

    public function test_rejects_non_image(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, ['Content-Type' => 'text/html'])]);
        $this->putJson('/api/v1/aktionen/x', ['titel' => ['de' => 'T'], 'bild_url' => 'https://dashboard.example/x'], ['X-Api-Key' => $this->write])
            ->assertUnprocessable();
        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_admin_can_create_and_revoke_key(): void
    {
        $admin = \App\Models\User::create(['name' => 'A B', 'email' => 'a@test.example', 'password' => 'x-passwort-lang', 'role' => 'superadmin']);
        $this->actingAs($admin)->post(route('admin.api-keys.store'), ['name' => 'Dashboard', 'can_write' => '1'])->assertSessionHas('new_key');
        $this->get(route('admin.api-keys.index'))->assertOk();
        $this->get(route('admin.promotions.index'))->assertOk();
        $this->delete(route('admin.api-keys.destroy', ApiKey::latest('id')->first()))->assertRedirect();
        $this->assertDatabaseCount('api_keys', 2);
    }
}
