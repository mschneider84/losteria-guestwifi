<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Activity;
use App\Support\Legal;
use App\Support\Media;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Standard-Logo und Rechtstexte (Nutzungsbedingungen, Datenschutz, Impressum) der Gästeseite. */
class GuestPageController extends Controller
{
    public function edit(): View
    {
        return view('admin.guest-page', [
            'texts' => Legal::all(),
            'logoPath' => Settings::get('brand.logo_path'),
            'previewLocation' => Location::mode((bool) session('demo'))->orderBy('name')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_remove' => ['nullable', 'boolean'],
            'texts' => ['array'],
            'texts.*' => ['array'],
            'texts.*.*' => ['nullable', 'string', 'max:30000'],
        ]);

        if ($request->boolean('logo_remove') && ($old = Settings::get('brand.logo_path'))) {
            Media::disk()->delete($old);
            Settings::set('brand.logo_path', null);
        }
        if ($request->hasFile('logo')) {
            if ($old = Settings::get('brand.logo_path')) {
                Media::disk()->delete($old);
            }
            Settings::set('brand.logo_path', $request->file('logo')->store('logos', Media::diskName()));
        }

        $before = Legal::all();
        foreach (array_keys(Legal::PAGES) as $key) {
            if (isset($data['texts'][$key])) {
                Legal::save($key, $data['texts'][$key], $request->user()?->id);
            }
        }
        // Geänderte Nutzungsbedingungen/Datenschutz = neue Version der Einwilligung (wird bei Newsletter-Anmeldungen gespeichert)
        $after = Legal::all();
        if (($before['terms'] ?? []) != ($after['terms'] ?? [])) {
            Settings::set('privacy.consent_version', now()->format('Y-m-d H:i'));
        }
        Activity::log('guest-page.updated');

        return back()->with('ok', __('Gästeseite gespeichert.'));
    }
}
