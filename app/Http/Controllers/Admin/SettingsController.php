<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Unifi\UnifiManager;
use App\Support\Activity;
use App\Support\Settings;
use App\Support\UnifiConsoles;
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
        // Schlüssel da, Konsolen noch nicht geladen: gleich abholen, damit die Auswahl erscheint
        $consoleError = UnifiConsoles::error();
        if (! $consoleError && ! UnifiConsoles::all() && $this->cloudKeySet()) {
            $consoleError = $this->refreshConsoles();
        }

        return view('admin.settings', [
            'groups' => self::GROUPS, 'fields' => Settings::FIELDS,
            'consoles' => UnifiConsoles::labels(), 'consoleError' => $consoleError,
        ]);
    }

    private function cloudKeySet(): bool
    {
        return Settings::get('unifi.mode') !== 'local' && Settings::has('unifi.cloud_api_key');
    }

    /** Konsolen laden; bei genau einer Konsole wird sie als Standard übernommen. Liefert die Fehlermeldung oder null. */
    private function refreshConsoles(): ?string
    {
        $error = UnifiConsoles::refresh();
        $all = UnifiConsoles::all();
        if (! $error && count($all) === 1 && ! Settings::has('unifi.console_id')) {
            Settings::set('unifi.console_id', array_key_first($all));
        }

        return $error;
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

        if (in_array('unifi.cloud_api_key', $changed, true) && $this->cloudKeySet()) {
            UnifiConsoles::forget();
            if ($error = $this->refreshConsoles()) {
                return back()->with('error', __('Gespeichert, aber die Konsolen ließen sich nicht laden: :m', ['m' => $error]));
            }

            return back()->with('ok', __('Gespeichert. :n Konsole(n) gefunden – ordne sie jetzt unter Standorte → Bearbeiten dem jeweiligen Restaurant zu.', ['n' => count(UnifiConsoles::all())]));
        }

        return back()->with('ok', __('Einstellungen gespeichert.'));
    }

    public function loadConsoles(Request $request): RedirectResponse
    {
        if ($request->filled('unifi__cloud_api_key')) {
            Settings::set('unifi.cloud_api_key', $request->input('unifi__cloud_api_key'));
        }
        UnifiConsoles::forget();
        if ($error = $this->refreshConsoles()) {
            return back()->with('error', $error);
        }

        return back()->with('ok', __(':n Konsole(n) gefunden. Ordne sie unter Standorte → Bearbeiten dem jeweiligen Restaurant zu.', ['n' => count(UnifiConsoles::all())]));
    }

    public function testUnifi(UnifiManager $unifi): RedirectResponse
    {
        $demo = (bool) session('demo');
        Cache::forget('unifi.session');
        $locations = \App\Models\Location::mode($demo)->where('active', true)->orderBy('name')->get();
        // Cloud-Modus: jede Konsole einzeln prüfen (eine UDM je Restaurant)
        if (! $demo && Settings::get('unifi.mode') !== 'local' && $locations->isNotEmpty()) {
            $lines = [];
            $ok = true;
            foreach ($locations as $location) {
                try {
                    $sites = $unifi->forLocation($location)->sites();
                    $lines[] = "✓ {$location->name}: ".implode(', ', $sites);
                } catch (Throwable $e) {
                    $ok = false;
                    $lines[] = "✗ {$location->name}: ".$e->getMessage();
                }
            }
            Cache::put('unifi.health', ['ok' => $ok, 'at' => now()->toDateTimeString(), 'error' => $ok ? null : implode(' | ', $lines)], 600);

            return back()->with($ok ? 'ok' : 'error', implode(' · ', $lines));
        }
        try {
            $sites = $unifi->client($demo)->sites();
            Cache::put('unifi.health', ['ok' => true, 'at' => now()->toDateTimeString(), 'sites' => $sites], 600);

            return back()->with('ok', __('Verbindung klappt. Gefundene Sites: :s', ['s' => implode(', ', array_map(
                fn ($k, $v) => "$v ($k)", array_keys($sites), $sites))]));
        } catch (Throwable $e) {
            Cache::put('unifi.health', ['ok' => false, 'at' => now()->toDateTimeString(), 'error' => $e->getMessage()], 600);

            return back()->with('error', __('Verbindung fehlgeschlagen: :m', ['m' => $e->getMessage()]));
        }
    }
}
