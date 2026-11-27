<?php

namespace App\Http\Controllers\Admin;

use App\Demo\DemoData;
use App\Http\Controllers\Controller;
use App\Models\Meldung;
use App\Support\Activity;
use App\Support\Settings;
use App\Support\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SystemController extends Controller
{
    public function status(): View
    {
        $checks = [];
        $checks[] = $this->check(__('Datenbank'), fn () => DB::select('select 1') && true);
        $checks[] = $this->check(__('Cache'), function () {
            Cache::put('health.ping', 1, 10);

            return Cache::get('health.ping') === 1;
        });
        $heartbeat = Cache::get('scheduler.heartbeat');
        $checks[] = ['name' => __('Zeitplan (Heartbeat)'), 'ok' => $heartbeat && now()->diffInMinutes($heartbeat) < 10,
            'detail' => $heartbeat ? __('zuletzt :t', ['t' => \Carbon\Carbon::parse($heartbeat)->format('d.m.Y H:i')]) : __('noch kein Lauf – in Laravel Cloud den Scheduler einschalten')];
        $locations = \App\Models\Location::mode(false)->where('active', true)->orderBy('name')->get();
        if (Settings::get('unifi.mode') !== 'local' && $locations->isNotEmpty()) {
            // Eine UDM je Restaurant: Verbindung je Standort
            foreach ($locations as $l) {
                $h = Cache::get("unifi.health.{$l->id}");
                $hasConsole = $l->unifi_console_id || Settings::has('unifi.console_id');
                $hasKey = $l->unifi_api_key || Settings::has('unifi.cloud_api_key');
                $checks[] = ['name' => __('UniFi · :n', ['n' => $l->name]), 'ok' => $h['ok'] ?? ((! $hasKey || ! $hasConsole) ? false : null),
                    'detail' => ! $hasKey ? __('API-Schlüssel fehlt (Einstellungen)') : (! $hasConsole ? __('keine Konsole gewählt (Standorte → Bearbeiten)')
                        : ($h ? (($h['ok'] ? __('erreichbar') : $h['error']).' · '.\Carbon\Carbon::parse($h['at'])->format('d.m. H:i')) : __('noch nicht geprüft')))];
            }
        } else {
            $unifi = Cache::get('unifi.health');
            $configured = Settings::get('unifi.mode') === 'local' ? Settings::has('unifi.username') : Settings::has('unifi.cloud_api_key');
            $checks[] = ['name' => __('UniFi-Verbindung'), 'ok' => $unifi['ok'] ?? null,
                'detail' => $unifi ? (($unifi['ok'] ? __('erreichbar') : $unifi['error']).' · '.\Carbon\Carbon::parse($unifi['at'])->format('d.m. H:i'))
                    : ($configured ? __('noch nicht geprüft') : __('Zugang fehlt in den Einstellungen'))];
        }
        $net = Cache::get('network.last_run');
        $netErrors = collect($net['result'] ?? [])->filter(fn ($r) => str_starts_with($r, 'Fehler'));
        $checks[] = ['name' => __('Netzwerk-Auswertung'), 'ok' => $net ? ($netErrors->isEmpty() && now()->diffInMinutes($net['at']) < 15) : null,
            'detail' => $net ? __('zuletzt :t', ['t' => \Carbon\Carbon::parse($net['at'])->format('d.m.Y H:i')]).($netErrors->isNotEmpty() ? ' · '.$netErrors->keys()->implode(', ').': '.$netErrors->first() : '') : __('noch kein Lauf')];
        if (Settings::get('modules.newsletter')) {
            $checks[] = ['name' => __('Mail-Absender'), 'ok' => Settings::has('mail.from_address'),
                'detail' => Settings::get('mail.from_address') ?: __('nicht eingetragen (Einstellungen → E-Mail)')];
        }

        return view('admin.status', [
            'checks' => $checks,
            'version' => Version::label(),
            'logs' => DB::table('system_logs')->latest('id')->limit(15)->get(),
            'lastPurge' => Cache::get('privacy.last_purge'),
            'openAi' => Meldung::where('status', 'ai')->count(),
        ]);
    }

    public function news(Request $request): View
    {
        $entries = collect(config('changelog'))
            ->filter(fn ($e) => in_array($e['audience'] ?? 'all', ['all', 'admin'], true))
            ->groupBy('version');
        $request->user()->forceFill(['changelog_seen_version' => Version::version()])->save();

        return view('admin.news', compact('entries'));
    }

    public function toggleDemo(Request $request): RedirectResponse
    {
        $on = ! session('demo');
        if ($on) {
            DemoData::ensure();
        }
        session(['demo' => $on]);

        return back()->with('ok', $on ? __('Demo-Modus an: du siehst erfundene Daten, an der UDM wird nichts geändert.') : __('Demo-Modus aus.'));
    }

    public function storeMeldung(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:wish,bug,praise',
            'description' => 'required|max:5000',
            'page_url' => 'nullable|max:255',
            'page_title' => 'nullable|max:255',
        ]);
        Meldung::create($data + [
            'title' => \Illuminate\Support\Str::limit(strtok($data['description'], "\n"), 80),
            'user_id' => $request->user()->id,
            'app_version' => Version::version().' · Build '.Version::build(),
        ]);

        return back()->with('ok', __('Danke! Deine Meldung ist angekommen.'));
    }

    public function meldungen(): View
    {
        return view('admin.meldungen', ['meldungen' => Meldung::with('user:id,name')->latest()->paginate(30)]);
    }

    public function meldungStatus(Request $request, Meldung $meldung): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:new,review,ai,planned,done,rejected', 'reply' => 'nullable|max:2000']);
        $meldung->update($data + ($data['status'] === 'done' ? ['done_in_version' => Version::version()] : []));
        Activity::log('meldung.status', '#'.$meldung->id, ['status' => $data['status']]);

        return back()->with('ok', __('Status gesetzt.'));
    }

    private function check(string $name, callable $fn): array
    {
        try {
            return ['name' => $name, 'ok' => (bool) $fn(), 'detail' => __('in Ordnung')];
        } catch (Throwable $e) {
            return ['name' => $name, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
