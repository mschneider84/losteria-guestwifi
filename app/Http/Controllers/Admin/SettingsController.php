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
        // Schlüssel da, aber noch keine Konsole gewählt: Konsolen gleich abholen, damit die Auswahl erscheint
        $consoleError = Cache::get('unifi.consoles.error');
        if (! Cache::has('unifi.consoles') && ! $consoleError && $this->needsConsole()) {
            $consoleError = $this->refreshConsoles();
        }

        return view('admin.settings', [
            'groups' => self::GROUPS, 'fields' => Settings::FIELDS,
            'consoles' => Cache::get('unifi.consoles', []), 'consoleError' => $consoleError,
        ]);
    }

    private function needsConsole(): bool
    {
        return Settings::get('unifi.mode') !== 'local' && Settings::has('unifi.cloud_api_key') && ! Settings::has('unifi.console_id');
    }

    /** Konsolen des UI-Kontos laden; bei genau einer Konsole wird sie gleich gewählt. Liefert die Fehlermeldung oder null. */
    private function refreshConsoles(): ?string
    {
        try {
            $consoles = \App\Services\Unifi\UnifiCloudClient::fromSettings()->consoles();
            Cache::put('unifi.consoles', $consoles, 3600);
            Cache::forget('unifi.consoles.error');
            if (count($consoles) === 1 && ! Settings::has('unifi.console_id')) {
                Settings::set('unifi.console_id', array_key_first($consoles));
            }

            return $consoles ? null : __('Mit diesem Schlüssel ist keine Konsole verbunden. Ist die UDM SE im selben UI-Konto (unifi.ui.com)?');
        } catch (Throwable $e) {
            Cache::put('unifi.consoles.error', $e->getMessage(), 300);

            return $e->getMessage();
        }
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

        if (in_array('unifi.cloud_api_key', $changed, true)) {
            Cache::forget('unifi.consoles');
            Cache::forget('unifi.consoles.error');
        }
        if ($this->needsConsole()) {
            if ($error = $this->refreshConsoles()) {
                return back()->with('error', __('Gespeichert, aber die Konsolen ließen sich nicht laden: :m', ['m' => $error]));
            }
            if (! Settings::has('unifi.console_id')) {
                return back()->with('ok', __('Gespeichert. Bitte jetzt unter „Konsole (UDM SE)“ deine UDM SE auswählen und nochmal speichern.'));
            }

            return back()->with('ok', __('Gespeichert. Die UDM SE wurde als Konsole übernommen.'));
        }

        return back()->with('ok', __('Einstellungen gespeichert.'));
    }

    public function loadConsoles(Request $request): RedirectResponse
    {
        if ($request->filled('unifi__cloud_api_key')) {
            Settings::set('unifi.cloud_api_key', $request->input('unifi__cloud_api_key'));
        }
        Cache::forget('unifi.consoles');
        Cache::forget('unifi.consoles.error');
        if ($error = $this->refreshConsoles()) {
            return back()->with('error', $error);
        }
        $n = count(Cache::get('unifi.consoles', []));

        return back()->with('ok', $n === 1 && Settings::has('unifi.console_id')
            ? __('Konsole gefunden und übernommen.')
            : __(':n Konsolen gefunden. Bitte die UDM SE auswählen und speichern.', ['n' => $n]));
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
