<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Unifi\UnifiManager;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public const GROUPS = [
        'unifi' => '📡 UniFi / UDM SE',
        'network' => '📶 Netzwerk-Auswertungen',
        'portal' => '🌐 Gästeseite',
        'privacy' => '🔒 Datenschutz & Löschfristen',
        'mail' => '✉️ E-Mail',
        'brand' => '🎨 Branding',
    ];

    public function index(): View
    {
        return view('admin.settings', ['groups' => self::GROUPS, 'fields' => Settings::FIELDS, 'consoles' => Cache::get('unifi.consoles', [])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $changed = [];
        foreach (Settings::FIELDS as $key => [$group, $label, $type, $default, $secret]) {
            $input = str_replace('.', '__', $key);
            if ($type === 'bool') {
                $value = $request->boolean($input);
            } else {
                $value = $request->input($input);
                if ($secret && ($value === null || $value === '')) {
                    continue; // write-only: leeres Feld = unverändert
                }
            }
            if ((string) Settings::get($key) !== (string) $value || $secret) {
                Settings::set($key, $value);
                $changed[] = $key;
            }
        }
        if (array_intersect($changed, ['unifi.url', 'unifi.username', 'unifi.password'])) {
            Cache::forget('unifi.session');
            Cache::forget('unifi.health');
        }
        Activity::log('settings.updated', null, ['keys' => $changed]);

        return back()->with('ok', __('Einstellungen gespeichert.'));
    }

    public function loadConsoles(Request $request): RedirectResponse
    {
        if ($request->filled('unifi__cloud_api_key')) {
            Settings::set('unifi.cloud_api_key', $request->input('unifi__cloud_api_key'));
        }
        try {
            $consoles = \App\Services\Unifi\UnifiCloudClient::fromSettings()->consoles();
            Cache::put('unifi.consoles', $consoles, 3600);
            if (count($consoles) === 1 && ! Settings::has('unifi.console_id')) {
                Settings::set('unifi.console_id', array_key_first($consoles));
            }

            return back()->with('ok', __(':n Konsole(n) gefunden. Bitte die UDM SE auswählen und speichern.', ['n' => count($consoles)]));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function testUnifi(UnifiManager $unifi): RedirectResponse
    {
        try {
            Cache::forget('unifi.session');
            $sites = $unifi->client((bool) session('demo'))->sites();
            Cache::put('unifi.health', ['ok' => true, 'at' => now()->toDateTimeString(), 'sites' => $sites], 600);

            return back()->with('ok', __('Verbindung klappt. Gefundene Sites: :s', ['s' => implode(', ', array_map(
                fn ($k, $v) => "$v ($k)", array_keys($sites), $sites))]));
        } catch (Throwable $e) {
            Cache::put('unifi.health', ['ok' => false, 'at' => now()->toDateTimeString(), 'error' => $e->getMessage()], 600);

            return back()->with('error', __('Verbindung fehlgeschlagen: :m', ['m' => $e->getMessage()]));
        }
    }
}
