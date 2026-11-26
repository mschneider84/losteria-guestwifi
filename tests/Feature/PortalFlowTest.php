<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmation;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\NewsletterContact;
use App\Services\Unifi\DemoUnifiClient;
use App\Services\Unifi\UnifiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalFlowTest extends TestCase
{
    use RefreshDatabase;

    private DemoUnifiClient $udm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->udm = new DemoUnifiClient;
        $this->app->instance(UnifiClient::class, $this->udm);
        Mail::fake();
    }

    private function location(array $attrs = []): Location
    {
        return Location::create($attrs + ['name' => 'Test-Osteria', 'slug' => 'test', 'unifi_site' => 'default',
            'session_minutes' => 240, 'down_kbps' => 20000, 'up_kbps' => 5000]);
    }

    public function test_unifi_redirect_stores_device_and_shows_page(): void
    {
        $this->location();
        $this->get('/guest/s/default/?id=AA:BB:CC:DD:EE:FF&ap=11:22:33:44:55:66&ssid=Osteria-Gast&url=http://example.com')
            ->assertRedirect(route('portal.show', 'test'))
            ->assertSessionHas('portal.1.mac', 'aa:bb:cc:dd:ee:ff');

        $this->get(route('portal.show', ['location' => 'test', 'lang' => 'it']))->assertOk()->assertSee('Connettiti gratis');
    }

    public function test_invalid_mac_shows_hint_instead_of_form(): void
    {
        $this->location();
        $this->get('/guest/s/default/?id=kaputt')->assertOk()->assertDontSee('name="terms"', false);
    }

    public function test_click_login_authorizes_guest_with_limits(): void
    {
        $this->location();
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff&ap=11:22:33:44:55:66');

        $this->post(route('portal.connect', 'test'), ['terms' => '1', 'lang' => 'de'])
            ->assertRedirect(route('portal.done', ['location' => 'test', 'lang' => 'de']));

        $this->assertSame([['site' => 'default', 'mac' => 'aa:bb:cc:dd:ee:ff', 'minutes' => 240, 'upKbps' => 5000,
            'downKbps' => 20000, 'quotaMb' => null, 'apMac' => '11:22:33:44:55:66']], $this->udm->calls);
        $session = GuestSession::sole();
        $this->assertSame('authorized', $session->status);
        $this->assertNotSame('aa:bb:cc:dd:ee:ff', $session->device_hash, 'MAC darf nie im Klartext gespeichert werden');
        Mail::assertNothingSent();
    }

    public function test_terms_are_required(): void
    {
        $this->location();
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->post(route('portal.connect', 'test'), [])->assertSessionHasErrors('terms');
        $this->assertSame([], $this->udm->calls);
    }

    public function test_newsletter_double_opt_in(): void
    {
        $this->location();
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->post(route('portal.connect', 'test'), ['terms' => '1', 'newsletter' => '1', 'email' => 'gast@mail.example', 'lang' => 'en']);

        $contact = NewsletterContact::sole();
        $this->assertSame('pending', $contact->status);
        Mail::assertSent(NewsletterConfirmation::class, fn ($m) => $m->hasTo('gast@mail.example'));

        $this->get(route('newsletter.confirm', $contact->token))->assertOk()->assertSee('Thank you');
        $this->assertSame('confirmed', $contact->fresh()->status);

        $this->get(route('newsletter.unsubscribe', $contact->token))->assertOk();
        $this->assertSame('unsubscribed', $contact->fresh()->status);
    }

    public function test_returning_guest_is_recognised(): void
    {
        $this->location();
        foreach ([1, 2] as $_) {
            $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
            $this->post(route('portal.connect', 'test'), ['terms' => '1']);
        }
        $this->assertSame([false, true], GuestSession::orderBy('id')->pluck('is_returning')->all());
    }

    public function test_failed_authorization_shows_error_code(): void
    {
        $this->location();
        $this->app->instance(UnifiClient::class, new class extends DemoUnifiClient {
            public function authorizeGuest(string $site, string $mac, int $minutes, ?int $upKbps = null, ?int $downKbps = null, ?int $quotaMb = null, ?string $apMac = null): void
            {
                throw new \App\Services\Unifi\UnifiException('UDM nicht erreichbar');
            }
        });
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->post(route('portal.connect', 'test'), ['terms' => '1'])->assertSessionHas('portal_error');
        $this->assertSame('failed', GuestSession::sole()->status);
        $this->assertDatabaseCount('system_logs', 1);
    }

    public function test_preview_never_touches_the_udm(): void
    {
        $this->location();
        $this->get(route('portal.preview', 'test'))->assertRedirect();
        $this->post(route('portal.connect', 'test'), ['terms' => '1', 'newsletter' => '1', 'email' => 'x@mail.example']);
        $this->assertSame([], $this->udm->calls);
        $this->assertTrue(GuestSession::sole()->is_demo);
        Mail::assertNothingSent();
    }
}
