<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\NewsletterContact;
use App\Models\User;
use App\Services\Unifi\DemoUnifiClient;
use App\Services\Unifi\UnifiClient;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestPageTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->app->instance(UnifiClient::class, new DemoUnifiClient);
        $this->location = Location::create(['name' => 'Osteria Test', 'slug' => 'test', 'unifi_site' => 'default', 'newsletter_enabled' => true]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@test.example', 'password' => 'passwort-lang-genug', 'role' => 'superadmin']);
    }

    public function test_legal_texts_are_edited_and_shown_on_guest_page(): void
    {
        $this->actingAs($this->admin())->put(route('admin.guest-page.update'), ['texts' => [
            'terms' => ['de' => "## Regeln\n\nBitte **fair** nutzen.", 'en' => 'Please use fairly.'],
            'imprint' => ['de' => 'L\'Osteria GmbH, Wien <script>alert(1)</script>'],
        ]])->assertSessionHas('ok');
        auth()->logout();

        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->get(route('portal.show', ['location' => 'test', 'lang' => 'de']))->assertOk()
            ->assertSee(route('portal.legal', ['test', 'nutzungsbedingungen', 'lang' => 'de']), false)
            ->assertSee(route('portal.legal', ['test', 'impressum', 'lang' => 'de']), false);

        $this->get(route('portal.legal', ['test', 'nutzungsbedingungen', 'lang' => 'de']))->assertOk()->assertSee('<strong>fair</strong>', false)->assertSee('<h2>Regeln</h2>', false);
        $this->get(route('portal.legal', ['test', 'nutzungsbedingungen', 'lang' => 'en']))->assertOk()->assertSee('Please use fairly.');
        $this->get(route('portal.legal', ['test', 'nutzungsbedingungen', 'lang' => 'it']))->assertOk()->assertSee('fair'); // Fallback Deutsch
        $this->get(route('portal.legal', ['test', 'impressum']))->assertOk()->assertDontSee('<script>', false);
        $this->get(route('portal.legal', ['test', 'datenschutz']))->assertOk()->assertSee('fair'); // gemeinsamer Text
        $this->assertNotSame('2026-11', Settings::get('privacy.consent_version'));
        $this->get(route('admin.guest-page.edit'))->assertDontSee('Datenschutzerklärung</strong>', false);
    }

    public function test_existing_privacy_text_is_merged_into_terms(): void
    {
        \Illuminate\Support\Facades\DB::table('legal_texts')->insert([
            ['key' => 'terms', 'content' => json_encode(['de' => 'Regeln']), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'privacy', 'content' => json_encode(['de' => 'Datenschutz-Text', 'en' => 'Privacy text']), 'created_at' => now(), 'updated_at' => now()],
        ]);
        (require database_path('migrations/2026_11_30_000200_merge_privacy_into_terms.php'))->up();
        $this->assertSame("Regeln\n\nDatenschutz-Text", \App\Support\Legal::text('terms', 'de'));
        $this->assertSame('Privacy text', \App\Support\Legal::text('terms', 'en'));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('legal_texts')->where('key', 'privacy')->count());
    }

    public function test_default_logo_upload_is_used_when_location_has_none(): void
    {
        $this->actingAs($this->admin())->put(route('admin.guest-page.update'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 100)])->assertSessionHas('ok');
        $this->assertNotNull(Settings::get('brand.logo_path'));
        $this->assertStringContainsString('/bild/logo?v=', $this->location->fresh()->logoUrl());
        $this->get($this->location->fresh()->logoUrl())->assertOk();
        $this->get(route('admin.guest-page.edit'))->assertOk()->assertSee('Logo entfernen');
    }

    public function test_newsletter_module_off_by_default(): void
    {
        $this->get('/guest/s/default/?id=aa:bb:cc:dd:ee:ff');
        $this->get(route('portal.show', 'test'))->assertOk()->assertDontSee('name="newsletter"', false);
        $this->post(route('portal.connect', 'test'), ['terms' => '1', 'newsletter' => '1', 'email' => 'gast@mail.example']);
        $this->assertSame(0, NewsletterContact::count());

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.contacts.index'), false);
        $this->get(route('admin.status'))->assertDontSee('Mail-Absender');

        Settings::set('modules.newsletter', true);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.contacts.index'), false);
    }
}
