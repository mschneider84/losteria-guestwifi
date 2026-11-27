<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\Location;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $demo = (bool) session('demo');
        $locations = Location::mode($demo)->orderBy('name')->get();
        // Zählungen gruppiert vorladen statt je Zeile
        $today = GuestSession::where('is_demo', $demo)->where('status', 'authorized')
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('location_id, COUNT(*) as n')->groupBy('location_id')->pluck('n', 'location_id');
        $online = GuestSession::where('is_demo', $demo)->where('status', 'authorized')->where('expires_at', '>', now())
            ->selectRaw('location_id, COUNT(*) as n')->groupBy('location_id')->pluck('n', 'location_id');

        return view('admin.locations.index', compact('locations', 'today', 'online'));
    }

    public function create(): View
    {
        return view('admin.locations.form', ['consoles' => $this->consoles(), 'location' => new Location([
            'session_minutes' => 240, 'brand_color' => '#b3261e', 'unifi_site' => 'default',
            'newsletter_enabled' => true, 'collect_email' => true, 'active' => true, 'down_kbps' => 20000, 'up_kbps' => 5000,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = Location::create($this->validated($request) + ['is_demo' => (bool) session('demo')]);
        $this->storeLogo($request, $location);
        Activity::log('location.created', $location->name);

        return redirect()->route('admin.locations.index')->with('ok', __('Standort angelegt.'));
    }

    public function edit(Location $location): View
    {
        $this->guardMode($location);

        return view('admin.locations.form', ['location' => $location, 'consoles' => $this->consoles()]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->guardMode($location);
        $location->update($this->validated($request, $location));
        $this->storeLogo($request, $location);
        Activity::log('location.updated', $location->name);

        return redirect()->route('admin.locations.index')->with('ok', __('Gespeichert.'));
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'name' => 'required|max:100',
            'city' => 'nullable|max:60',
            'slug' => 'nullable|alpha_dash|max:60|unique:locations,slug'.($location ? ','.$location->id : ''),
            'unifi_site' => 'required|alpha_dash|max:40',
            'unifi_console_id' => 'nullable|string|max:120',
            'unifi_api_key' => 'nullable|string|max:500',
            'unifi_api_key_remove' => 'nullable|boolean',
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'session_minutes' => 'required|integer|min:15|max:1440',
            'down_kbps' => 'nullable|integer|min:256|max:1000000',
            'up_kbps' => 'nullable|integer|min:256|max:1000000',
            'quota_mb' => 'nullable|integer|min:10|max:100000',
            'redirect_url' => 'nullable|url|max:255',
            'headline.*' => 'nullable|max:120',
            'welcome_text.*' => 'nullable|max:300',
            'logo' => 'nullable|image|max:2048',
        ]);
        unset($data['logo'], $data['unifi_api_key_remove']);
        // Schlüssel ist write-only: leer = unverändert, Haken = entfernen
        if (blank($data['unifi_api_key'] ?? null)) {
            unset($data['unifi_api_key']);
        }
        if ($request->boolean('unifi_api_key_remove')) {
            $data['unifi_api_key'] = null;
        }
        // Öffentliche IP der UDM merken – damit erkennt die Gästeseite das Restaurant
        $data['unifi_wan_ip'] = \App\Support\UnifiConsoles::ip($data['unifi_console_id'] ?? null) ?? ($location?->unifi_console_id === ($data['unifi_console_id'] ?? null) ? $location?->unifi_wan_ip : null);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        foreach (['newsletter_enabled', 'collect_email', 'active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['headline'] = array_filter($request->input('headline', []));
        $data['welcome_text'] = array_filter($request->input('welcome_text', []));

        return $data;
    }

    /** Verbindung zur Konsole dieses Standorts prüfen. */
    public function test(Location $location, \App\Services\Unifi\UnifiManager $unifi): RedirectResponse
    {
        $this->guardMode($location);
        try {
            $sites = $unifi->forLocation($location, $location->is_demo)->sites();
            \Illuminate\Support\Facades\Cache::put("unifi.health.{$location->id}", ['ok' => true, 'at' => now()->toDateTimeString()], 86400);

            return back()->with('ok', __('Verbindung klappt. Sites auf der Konsole: :s', ['s' => implode(', ', array_map(fn ($k, $v) => "$v ($k)", array_keys($sites), $sites))]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::put("unifi.health.{$location->id}", ['ok' => false, 'at' => now()->toDateTimeString(), 'error' => $e->getMessage()], 86400);

            return back()->with('error', __('Verbindung fehlgeschlagen: :m', ['m' => $e->getMessage()]));
        }
    }

    private function consoles(): array
    {
        if (! \App\Support\UnifiConsoles::all() && ! \App\Support\UnifiConsoles::error() && \App\Support\Settings::has('unifi.cloud_api_key')) {
            \App\Support\UnifiConsoles::refresh();
        }

        return \App\Support\UnifiConsoles::labels();
    }

    private function storeLogo(Request $request, Location $location): void
    {
        if ($request->hasFile('logo')) {
            $location->update(['logo_path' => $request->file('logo')->store('logos', \App\Support\Media::diskName())]);
        }
    }

    private function guardMode(Location $location): void
    {
        abort_if($location->is_demo !== (bool) session('demo'), 404);
    }
}
