<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\Location;
use App\Models\NewsletterContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_periods_are_applied(): void
    {
        $loc = Location::create(['name' => 'A', 'slug' => 'a']);
        $old = GuestSession::create(['location_id' => $loc->id, 'device_hash' => 'x']);
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        GuestSession::create(['location_id' => $loc->id, 'device_hash' => 'y']);
        $pending = NewsletterContact::create(['email' => 'a@mail.example', 'token' => 't1', 'status' => 'pending']);
        $pending->forceFill(['created_at' => now()->subDays(15)])->save();
        NewsletterContact::create(['email' => 'b@mail.example', 'token' => 't2', 'status' => 'confirmed', 'created_at' => now()->subYear()]);

        $this->artisan('portal:purge')->assertSuccessful();

        $this->assertSame(['y'], GuestSession::pluck('device_hash')->all());
        $this->assertSame(['b@mail.example'], NewsletterContact::pluck('email')->all());
    }
}
