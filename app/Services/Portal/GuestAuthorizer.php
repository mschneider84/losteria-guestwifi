<?php

namespace App\Services\Portal;

use App\Mail\NewsletterConfirmation;
use App\Models\GuestSession;
use App\Models\Location;
use App\Models\NewsletterContact;
use App\Services\Unifi\UnifiManager;
use App\Support\ErrorCode;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Ablauf einer Gast-Anmeldung: Freischalten an der UDM, protokollieren, ggf. Newsletter-Double-Opt-in. */
class GuestAuthorizer
{
    public function __construct(private readonly UnifiManager $unifi) {}

    /**
     * @param  array{mac:string, ap:?string, ssid:?string}  $device
     * @return array{ok:bool, session:GuestSession, error_code:?string}
     */
    public function authorize(Location $location, array $device, string $lang, ?string $newsletterEmail, string $ip, bool $demo): array
    {
        $hash = GuestSession::hashDevice($device['mac']);
        $returning = GuestSession::where('location_id', $location->id)->where('device_hash', $hash)->exists();

        $session = GuestSession::create([
            'location_id' => $location->id,
            'device_hash' => $hash,
            'ap_mac' => $device['ap'] ?? null,
            'ssid' => $device['ssid'] ?? null,
            'device_type' => $device['device_type'] ?? null,
            'language' => $lang,
            'method' => $newsletterEmail ? 'email' : 'click',
            'is_returning' => $returning,
            'status' => 'pending',
            'is_demo' => $demo,
        ]);

        try {
            $this->unifi->forLocation($location, $demo)->authorizeGuest(
                $location->unifi_site,
                $device['mac'],
                $location->session_minutes,
                $location->up_kbps,
                $location->down_kbps,
                $location->quota_mb,
                $device['ap'] ?? null,
            );
        } catch (Throwable $e) {
            $code = ErrorCode::make();
            $session->update(['status' => 'failed', 'error' => $code]);
            DB::table('system_logs')->insert([
                'level' => 'error', 'code' => $code,
                'message' => Str::limit('Freischaltung fehlgeschlagen: '.$e->getMessage(), 490),
                'context' => json_encode(['location' => $location->slug, 'site' => $location->unifi_site]),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['ok' => false, 'session' => $session, 'error_code' => $code];
        }

        $session->update([
            'status' => 'authorized',
            'authorized_at' => now(),
            'expires_at' => now()->addMinutes($location->session_minutes),
        ]);

        if ($newsletterEmail && $location->newsletter_enabled) {
            $this->subscribe($location, $newsletterEmail, $lang, $ip, $demo);
        }

        return ['ok' => true, 'session' => $session, 'error_code' => null];
    }

    private function subscribe(Location $location, string $email, string $lang, string $ip, bool $demo): void
    {
        $email = Str::lower(trim($email));
        $contact = NewsletterContact::firstOrNew(['email' => $email, 'is_demo' => $demo]);
        if ($contact->exists && $contact->status === 'confirmed') {
            return;
        }
        $contact->fill([
            'location_id' => $location->id,
            'language' => $lang,
            'token' => Str::random(48),
            'status' => 'pending',
            'consent_at' => now(),
            'consent_ip_hash' => hash('sha256', $ip.config('app.key')),
            'consent_text_version' => Settings::get('privacy.consent_version'),
            'unsubscribed_at' => null,
        ])->save();

        if (! $demo) {
            Mail::to($email)->send(new NewsletterConfirmation($contact, $location));
        }
    }
}
