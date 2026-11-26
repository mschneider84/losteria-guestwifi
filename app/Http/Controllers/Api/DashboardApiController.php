<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Promotion;
use App\Services\Network\NetworkStats;
use App\Services\PromotionImporter;
use App\Services\StatsService;
use App\Support\Activity;
use App\Support\Version;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Schnittstelle für das L'Osteria Dashboard – Spezifikation: docs/dashboard-schnittstelle.md */
class DashboardApiController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'portal' => config('app.name'),
            'version' => Version::version(),
            'build' => Version::build(),
            'standorte' => Location::where('is_demo', false)->count(),
        ]);
    }

    public function locations(): JsonResponse
    {
        return response()->json(['daten' => Location::where('is_demo', false)->orderBy('name')->get()->map(fn (Location $l) => [
            'slug' => $l->slug, 'name' => $l->name, 'stadt' => $l->city, 'unifi_site' => $l->unifi_site, 'aktiv' => $l->active,
            'zeitlimit_minuten' => $l->session_minutes, 'download_kbps' => $l->down_kbps, 'upload_kbps' => $l->up_kbps,
        ])]);
    }

    public function stats(Request $request, StatsService $stats): JsonResponse
    {
        $data = $request->validate([
            'von' => ['nullable', 'date_format:Y-m-d'],
            'bis' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:von'],
            'standort' => ['nullable', 'string', 'exists:locations,slug'],
        ]);
        $to = isset($data['bis']) ? CarbonImmutable::parse($data['bis'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = isset($data['von']) ? CarbonImmutable::parse($data['von'])->startOfDay() : $to->subDays(29)->startOfDay();
        if ($from->diffInDays($to) > 366) {
            return response()->json(['error' => 'Zeitraum höchstens 366 Tage.'], 422);
        }
        $location = isset($data['standort']) ? Location::where('slug', $data['standort'])->where('is_demo', false)->firstOrFail() : null;

        // Vergangene Zeiträume kurz zwischenspeichern
        $key = 'api.stats.'.md5(json_encode([$from, $to, $location?->id]));
        $result = Cache::remember($key, $to->isPast() ? 600 : 60, fn () => $stats->summary($from, $to, $location));

        return response()->json($result);
    }

    /** Live-Auslastung je Standort und Bereich. */
    public function occupancy(Request $request, NetworkStats $network): JsonResponse
    {
        $location = $this->locationParam($request);

        return response()->json(['daten' => $network->live(false, $location)]);
    }

    /** Netzwerk-Auswertung eines Zeitraums (Bereiche, Auslastung, Verweildauer, Stammgäste, Aktionen, Technik). */
    public function network(Request $request, NetworkStats $network): JsonResponse
    {
        $data = $request->validate([
            'von' => ['nullable', 'date_format:Y-m-d'],
            'bis' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:von'],
        ]);
        $location = $this->locationParam($request);
        $to = isset($data['bis']) ? CarbonImmutable::parse($data['bis'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = isset($data['von']) ? CarbonImmutable::parse($data['von'])->startOfDay() : $to->subDays(29)->startOfDay();
        if ($from->diffInDays($to) > 92) {
            return response()->json(['error' => 'Zeitraum höchstens 92 Tage.'], 422);
        }

        $key = 'api.network.'.md5(json_encode([$from, $to, $location?->id]));

        return response()->json(Cache::remember($key, $to->isPast() ? 900 : 120, fn () => $network->analysis($from, $to, false, $location)));
    }

    private function locationParam(Request $request): ?Location
    {
        $request->validate(['standort' => ['nullable', 'string', 'max:100']]);
        $slug = $request->query('standort');

        return $slug ? Location::where('slug', $slug)->where('is_demo', false)->firstOrFail() : null;
    }

    public function promotions(): JsonResponse
    {
        return response()->json(['daten' => Promotion::where('is_demo', false)->orderByDesc('priority')->get()->map(fn (Promotion $p) => $this->present($p))]);
    }

    public function upsertPromotion(Request $request, string $id, PromotionImporter $importer): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]{1,100}$/', $id), 422, 'Ungültige Aktions-ID.');
        $langs = ['de', 'en', 'it'];
        $data = $request->validate([
            'titel' => ['required', 'array'], 'titel.de' => ['required', 'string', 'max:120'],
            'titel.*' => ['nullable', 'string', 'max:120'],
            'text' => ['nullable', 'array'], 'text.*' => ['nullable', 'string', 'max:400'],
            'button_text' => ['nullable', 'array'], 'button_text.*' => ['nullable', 'string', 'max:40'],
            'button_url' => ['nullable', 'url:https', 'max:255'],
            'bild_url' => ['nullable', 'url:https', 'max:500'],
            'standorte' => ['nullable', 'array'], 'standorte.*' => ['string', Rule::exists('locations', 'slug')->where('is_demo', false)],
            'start' => ['nullable', 'date'], 'ende' => ['nullable', 'date', 'after:start'],
            'wochentage' => ['nullable', 'array'], 'wochentage.*' => ['integer', 'between:1,7'],
            'uhrzeit_von' => ['nullable', 'date_format:H:i'], 'uhrzeit_bis' => ['nullable', 'date_format:H:i'],
            'platzierung' => ['nullable', Rule::in(['start', 'online', 'both'])],
            'prioritaet' => ['nullable', 'integer', 'between:-100,100'],
            'aktiv' => ['nullable', 'boolean'],
        ]);
        foreach (['titel', 'text', 'button_text'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = array_intersect_key(array_filter($data[$f]), array_flip($langs)) ?: null;
            }
        }

        try {
            $promotion = $importer->upsert($id, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        Activity::log('api.promotion.upsert', $id);

        return response()->json(['daten' => $this->present($promotion)], $promotion->wasRecentlyCreated ? 201 : 200);
    }

    public function deletePromotion(string $id, PromotionImporter $importer): JsonResponse
    {
        $promotion = Promotion::where('external_id', $id)->where('is_demo', false)->firstOrFail();
        $importer->delete($promotion);
        Activity::log('api.promotion.delete', $id);

        return response()->json(null, 204);
    }

    private function present(Promotion $p): array
    {
        return [
            'id' => $p->external_id, 'titel' => $p->title, 'text' => $p->text, 'button_text' => $p->cta_label, 'button_url' => $p->cta_url,
            'bild_url' => $p->image_source_url, 'bild_im_portal' => $p->imageUrl(), 'standorte' => $p->location_slugs,
            'start' => $p->starts_at?->toIso8601String(), 'ende' => $p->ends_at?->toIso8601String(),
            'wochentage' => $p->weekdays ? array_map('intval', explode(',', $p->weekdays)) : null,
            'uhrzeit_von' => $p->time_from, 'uhrzeit_bis' => $p->time_to, 'platzierung' => $p->placement,
            'prioritaet' => $p->priority, 'aktiv' => $p->active, 'geaendert' => $p->updated_at?->toIso8601String(),
        ];
    }
}
