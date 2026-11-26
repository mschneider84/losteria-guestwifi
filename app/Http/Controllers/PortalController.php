<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\NewsletterContact;
use App\Services\Portal\GuestAuthorizer;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gästeseite. UniFi leitet nicht angemeldete Gäste hierher weiter:
 * /guest/s/{site}/?id=<Geräte-MAC>&ap=<AP-MAC>&t=<Zeit>&url=<ursprüngliche Seite>&ssid=<WLAN>
 */
class PortalController extends Controller
{
    public const LANGS = ['de', 'en', 'it'];

    private const MAC = '/^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/i';

    public function landing(Request $request, string $site): View|RedirectResponse
    {
        $location = Location::mode(false)->where('unifi_site', $site)->where('active', true)->firstOrFail();

        $mac = (string) $request->query('id', '');
        if (! preg_match(self::MAC, $mac)) {
            return $this->render($request, $location, false, invalid: true);
        }

        $request->session()->put("portal.{$location->id}", [
            'mac' => str_replace('-', ':', strtolower($mac)),
            'ap' => preg_match(self::MAC, (string) $request->query('ap')) ? strtolower($request->query('ap')) : null,
            'ssid' => mb_substr((string) $request->query('ssid', ''), 0, 64) ?: null,
            'url' => filter_var($request->query('url'), FILTER_VALIDATE_URL) ?: null,
            'demo' => false,
        ]);

        return redirect()->route('portal.show', ['location' => $location->slug, 'lang' => $request->query('lang')]);
    }

    /** Vorschau ohne echte UDM: es wird nichts freigeschaltet. */
    public function preview(Request $request, Location $location): RedirectResponse
    {
        $request->session()->put("portal.{$location->id}", [
            'mac' => '02:00:00:'.implode(':', str_split(substr(md5((string) microtime()), 0, 6), 2)),
            'ap' => null, 'ssid' => 'Demo-Gast', 'url' => null, 'demo' => true,
        ]);

        return redirect()->route('portal.show', ['location' => $location->slug, 'lang' => $request->query('lang')]);
    }

    public function show(Request $request, Location $location): View
    {
        $device = $request->session()->get("portal.{$location->id}");

        return $this->render($request, $location, (bool) ($device['demo'] ?? false), invalid: ! $device);
    }

    public function connect(Request $request, Location $location, GuestAuthorizer $authorizer): RedirectResponse
    {
        $lang = $this->lang($request);
        app()->setLocale($lang);
        $device = $request->session()->get("portal.{$location->id}");
        if (! $device) {
            return redirect()->route('portal.show', ['location' => $location->slug, 'lang' => $lang]);
        }

        $data = $request->validate([
            'terms' => ['accepted'],
            'newsletter' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if:newsletter,1', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'max:0'], // Honeypot
        ], [], ['terms' => trans('portal.terms_short'), 'email' => trans('portal.email')]);

        $email = ($location->newsletter_enabled && ! empty($data['newsletter'])) ? $data['email'] : null;
        $result = $authorizer->authorize($location, $device, $lang, $email, (string) $request->ip(), (bool) $device['demo']);

        if (! $result['ok']) {
            return back()->withInput()->with('portal_error', $result['error_code']);
        }

        $request->session()->put("portal.{$location->id}.done", [
            'minutes' => $location->session_minutes,
            'newsletter' => (bool) $email,
        ]);

        return redirect()->route('portal.done', ['location' => $location->slug, 'lang' => $lang]);
    }

    public function done(Request $request, Location $location): View|RedirectResponse
    {
        $device = $request->session()->get("portal.{$location->id}");
        $done = $device['done'] ?? null;
        if (! $done) {
            return redirect()->route('portal.show', ['location' => $location->slug]);
        }
        $lang = $this->lang($request);
        app()->setLocale($lang);

        return view('portal.done', [
            'location' => $location,
            'lang' => $lang,
            'demo' => (bool) $device['demo'],
            'newsletter' => $done['newsletter'],
            'redirect' => $location->redirect_url ?: ($device['url'] ?? null),
        ]);
    }

    public function confirm(Request $request, string $token): View
    {
        $contact = NewsletterContact::where('token', $token)->firstOrFail();
        if ($contact->status === 'pending') {
            $contact->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        }
        app()->setLocale($contact->language);

        return view('portal.message', ['location' => $contact->location, 'lang' => $contact->language,
            'title' => trans('portal.confirmed_title'), 'text' => trans('portal.confirmed_text')]);
    }

    public function unsubscribe(Request $request, string $token): View
    {
        $contact = NewsletterContact::where('token', $token)->firstOrFail();
        $contact->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        app()->setLocale($contact->language);

        return view('portal.message', ['location' => $contact->location, 'lang' => $contact->language,
            'title' => trans('portal.unsubscribed_title'), 'text' => trans('portal.unsubscribed_text')]);
    }

    private function render(Request $request, Location $location, bool $demo, bool $invalid = false): View
    {
        $lang = $this->lang($request);
        app()->setLocale($lang);

        return view('portal.show', [
            'location' => $location,
            'lang' => $lang,
            'demo' => $demo,
            'invalid' => $invalid,
            'termsUrl' => Settings::get('portal.terms_url'),
            'privacyUrl' => Settings::get('portal.privacy_url'),
            'imprintUrl' => Settings::get('portal.imprint_url'),
        ]);
    }

    private function lang(Request $request): string
    {
        $lang = $request->query('lang') ?: $request->input('lang');
        if (in_array($lang, self::LANGS, true)) {
            $request->session()->put('portal.lang', $lang);

            return $lang;
        }

        return $request->session()->get('portal.lang') ?: ($request->getPreferredLanguage(self::LANGS) ?: 'de');
    }
}
