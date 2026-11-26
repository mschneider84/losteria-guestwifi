<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Promotion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array', 'text' => 'array', 'cta_label' => 'array', 'location_slugs' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean', 'is_demo' => 'boolean',
        ];
    }

    /** Aktive Aktionen für einen Standort zum Zeitpunkt $at (Zeitraum, Wochentage, Uhrzeit). */
    public static function currentFor(Location $location, string $placement, ?Carbon $at = null): ?self
    {
        $at ??= now();

        return self::query()
            ->where('active', true)->where('is_demo', $location->is_demo)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->whereIn('placement', [$placement, 'both'])
            ->orderByDesc('priority')->orderByDesc('id')
            ->get()
            ->first(fn (self $p) => $p->appliesTo($location, $at));
    }

    public function appliesTo(Location $location, Carbon $at): bool
    {
        if ($this->location_slugs && ! in_array($location->slug, $this->location_slugs, true)) {
            return false;
        }
        if ($this->weekdays && ! in_array((string) $at->isoWeekday(), explode(',', $this->weekdays), true)) {
            return false;
        }
        $time = $at->format('H:i');
        if ($this->time_from && $time < $this->time_from) {
            return false;
        }
        if ($this->time_to && $time > $this->time_to) {
            return false;
        }

        return true;
    }

    public function t(string $field, string $lang): ?string
    {
        $v = $this->{$field} ?? [];

        return $v[$lang] ?? $v['de'] ?? null;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? route('media.promotion', [$this->id, 'v' => substr(md5($this->image_path), 0, 8)]) : null;
    }

    public function count(Location $location, string $column): void
    {
        $day = now()->toDateString();
        DB::table('promotion_stats')->insertOrIgnore(['promotion_id' => $this->id, 'location_id' => $location->id, 'day' => $day]);
        DB::table('promotion_stats')->where(['promotion_id' => $this->id, 'location_id' => $location->id, 'day' => $day])->increment($column);
    }
}
