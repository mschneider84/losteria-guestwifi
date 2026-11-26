<?php

namespace Tests\Feature;

use App\Models\NewsletterContact;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'superadmin'): User
    {
        return User::create(['name' => 'Test Admin', 'email' => $role.'@test.example', 'password' => 'passwort-lang-genug', 'role' => $role]);
    }

    public function test_setup_only_without_users(): void
    {
        $this->get('/anmelden')->assertRedirect('/setup');
        $this->post('/setup', ['name' => 'Manfred', 'email' => 'm@test.example', 'password' => 'sehr-langes-passwort', 'password_confirmation' => 'sehr-langes-passwort'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame('superadmin', User::sole()->role);
        $this->post('/setup', ['name' => 'X', 'email' => 'x@test.example', 'password' => 'sehr-langes-passwort', 'password_confirmation' => 'sehr-langes-passwort'])->assertForbidden();
    }

    public function test_all_pages_render_in_demo_mode(): void
    {
        $this->actingAs($this->admin())->post(route('admin.demo'))->assertRedirect();
        foreach (['admin.dashboard', 'admin.locations.index', 'admin.locations.create', 'admin.contacts.index', 'admin.settings', 'admin.status', 'admin.users.index', 'admin.news', 'admin.meldungen.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.dashboard', ['tage' => 90]))->assertOk()->assertSee('Osteria Centrale');
    }

    public function test_export_contains_only_confirmed_contacts(): void
    {
        $this->actingAs($this->admin())->post(route('admin.demo'));
        $csv = $this->get(route('admin.contacts.export'))->assertOk()->streamedContent();
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(NewsletterContact::where('is_demo', true)->where('status', 'confirmed')->count() + 1, $lines);
    }

    public function test_viewer_cannot_change_settings(): void
    {
        $this->actingAs($this->admin('viewer'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.contacts.export'))->assertForbidden();
    }

    public function test_secrets_are_encrypted_and_write_only(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), ['unifi__username' => 'portal', 'unifi__password' => 'Geheim123']);
        $this->assertSame('Geheim123', Settings::get('unifi.password'));
        $this->assertDatabaseMissing('settings', ['value' => 'Geheim123']);

        $this->put(route('admin.settings.update'), ['unifi__username' => 'portal', 'unifi__password' => '']);
        Settings::flush();
        $this->assertSame('Geheim123', Settings::get('unifi.password'), 'leeres Feld behält das Passwort');
        $this->get(route('admin.settings'))->assertDontSee('Geheim123');
    }

    public function test_no_privilege_escalation(): void
    {
        $admin = $this->admin('admin');
        $admin->update(['role' => 'superadmin']);
        $manager = $this->admin('manager');
        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_feedback_is_stored(): void
    {
        $this->actingAs($this->admin())->post(route('admin.meldungen.store'), ['type' => 'wish', 'description' => "Export als Excel\nwäre super"])
            ->assertSessionHas('ok');
        $this->assertDatabaseHas('meldungen', ['title' => 'Export als Excel', 'status' => 'new']);
    }
}
