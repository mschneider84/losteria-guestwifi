<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Area;
use App\Models\Location;
use App\Models\User;
use App\Services\Unifi\DemoUnifiClient;
use App\Services\Unifi\UnifiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\Settings::flush();
    }

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@test.example', 'password' => 'passwort-lang-genug', 'role' => 'superadmin']);
    }

    public function test_global_texts_and_limits_with_location_override(): void
    {
        $this->app->instance(UnifiClient::class, new DemoUnifiClient);
        $a = Location::create(['name' => 'A', 'slug' => 'a', 'unifi_site' => 'default', 'use_default_limits' => true, 'session_minutes' => 240]);
        $b = Location::create(['name' => 'B', 'slug' => 'b', 'unifi_site' => 'default', 'session_minutes' => 90,
            'headline' => ['de' => 'Eigene Überschrift B']]);

        $this->actingAs($this->admin())->put(route('admin.guest-page.update'), [
            'ui' => ['headline' => ['de' => 'Willkommen bei L’Osteria'], 'connect' => ['de' => 'jetzt surfen']],
            'limits' => ['session_minutes' => 120, 'down_kbps' => 10000, 'up_kbps' => '', 'quota_mb' => 500],
        ])->assertSessionHas('ok');

        $a->refresh(); $b->refresh();
        $this->assertSame([120, 10000, null, 500], [$a->session_minutes, $a->down_kbps, $a->up_kbps, $a->quota_mb]);
        $this->assertSame(90, $b->session_minutes); // eigene Werte bleiben

        $this->get(route('portal.preview', ['location' => 'a', 'lang' => 'de']))->assertRedirect();
        $this->get(route('portal.show', ['location' => 'a', 'lang' => 'de']))->assertSee('Willkommen bei L’Osteria')->assertSee('jetzt surfen')->assertSee('2 h')->assertDontSee('Mbit/s');
        $this->get(route('portal.preview', ['location' => 'b', 'lang' => 'de']));
        $this->get(route('portal.show', ['location' => 'b', 'lang' => 'de']))->assertSee('Eigene Überschrift B')->assertSee('jetzt surfen');
        $this->get(route('portal.show', ['location' => 'a', 'lang' => 'en']))->assertSee(trans('portal.headline', [], 'en'), false);
    }

    public function test_location_form_can_switch_to_defaults_and_own_button_text(): void
    {
        $l = Location::create(['name' => 'C', 'slug' => 'c', 'unifi_site' => 'default', 'session_minutes' => 30]);
        $this->actingAs($this->admin())->put(route('admin.locations.update', $l), [
            'name' => 'C', 'slug' => 'c', 'unifi_site' => 'default', 'brand_color' => '#db052c', 'use_default_limits' => 1,
            'texts' => ['connect' => ['it' => 'Connettiti ora']],
        ])->assertRedirect();
        $l->refresh();
        $this->assertTrue($l->use_default_limits);
        $this->assertSame(240, $l->session_minutes);
        $this->assertSame('Connettiti ora', $l->guestText('connect', 'it'));
    }

    public function test_areas_are_managed_globally_and_renamed_everywhere(): void
    {
        $this->assertSame(['Innen', 'Terrasse', 'Bar', 'Eingang'], Area::names()); // aus der alten Einstellung übernommen
        $l = Location::create(['name' => 'D', 'slug' => 'd', 'unifi_site' => 'default']);
        $ap = AccessPoint::create(['location_id' => $l->id, 'unifi_id' => 'ap1', 'name' => 'AP 1', 'area' => 'Bar']);
        DB::table('network_snapshots')->insert(['location_id' => $l->id, 'area' => 'Bar', 'taken_at' => now(), 'clients' => 2, 'guests' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.areas.store'), ['name' => 'Wintergarten'])->assertSessionHas('ok');
        $bar = Area::where('name', 'Bar')->first();
        $rows = Area::all()->mapWithKeys(fn ($a) => [$a->id => ['name' => $a->id === $bar->id ? 'Bar & Lounge' : $a->name, 'color' => $a->color]])->all();
        $rows[Area::where('name', 'Eingang')->value('id')]['delete'] = 1;
        $this->actingAs($admin)->put(route('admin.areas.update'), ['areas' => $rows])->assertSessionHas('ok');

        $this->assertSame('Bar & Lounge', $ap->fresh()->area);
        $this->assertSame('Bar & Lounge', DB::table('network_snapshots')->value('area'));
        $this->assertNotContains('Eingang', Area::names());
        $this->assertContains('Wintergarten', Area::names());

        $this->actingAs($admin)->post(route('admin.areas.move', [Area::where('name', 'Wintergarten')->first(), 'up']));
        $this->assertSame('Wintergarten', Area::names()[2]);

        // Zuordnung nur aus der Liste
        $this->actingAs($admin)->put(route('admin.network.areas', $l), ['area' => [$ap->id => 'Irgendwas']])->assertSessionHasErrors();
        $this->actingAs($admin)->put(route('admin.network.areas', $l), ['area' => [$ap->id => 'Terrasse']])->assertSessionHas('ok');
        $this->assertSame('Terrasse', $ap->fresh()->area);
    }
}
