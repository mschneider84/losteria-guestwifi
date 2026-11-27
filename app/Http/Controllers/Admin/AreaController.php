<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessPoint;
use App\Models\Area;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Bereiche für alle Restaurants pflegen: anlegen, umbenennen, sortieren, löschen. */
class AreaController extends Controller
{
    public function index(): View
    {
        $areas = Area::orderBy('sort')->orderBy('name')->get();
        $used = AccessPoint::whereNotNull('area')->selectRaw('area, COUNT(*) as n')->groupBy('area')->pluck('n', 'area');

        return view('admin.areas', compact('areas', 'used'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40', 'not_in:gesamt,Gesamt', Rule::unique('areas', 'name')]]);
        Area::create(['name' => trim($data['name']), 'color' => Area::nextColor(), 'sort' => (int) Area::max('sort') + 1]);
        Activity::log('areas.created', $data['name']);

        return back()->with('ok', __('Bereich angelegt.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'areas' => ['array'],
            'areas.*.name' => ['required', 'string', 'max:40', 'not_in:gesamt,Gesamt', 'distinct'],
            'areas.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'areas.*.delete' => ['nullable', 'boolean'],
            'order' => ['nullable', 'string'],
        ]);
        $order = array_values(array_filter(explode(',', (string) ($data['order'] ?? ''))));
        foreach ($data['areas'] ?? [] as $id => $row) {
            $area = Area::find($id);
            if (! $area) {
                continue;
            }
            if (! empty($row['delete'])) {
                $area->remove();
                continue;
            }
            $area->rename(trim($row['name']));
            $pos = array_search((string) $id, $order, true);
            $area->update(['color' => $row['color'], 'sort' => $pos === false ? $area->sort : $pos]);
        }
        Activity::log('areas.updated');

        return back()->with('ok', __('Bereiche gespeichert.'));
    }

    /** Eine Zeile nach oben/unten schieben. */
    public function move(Area $area, string $dir): RedirectResponse
    {
        $list = Area::orderBy('sort')->orderBy('name')->get()->values();
        $i = $list->search(fn ($a) => $a->id === $area->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && $j >= 0 && $j < $list->count()) {
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            foreach ($list as $pos => $a) {
                $a->update(['sort' => $pos]);
            }
        }

        return back();
    }
}
