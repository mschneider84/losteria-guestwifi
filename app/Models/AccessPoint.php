<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Access Point eines Standorts; der Bereich (Innen, Terrasse, Bar …) wird im Admin zugeordnet. */
class AccessPoint extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'last_seen_at' => 'datetime', 'retries_pct' => 'float', 'cpu_pct' => 'float'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function areaLabel(): string
    {
        return $this->area ?: 'Ohne Bereich';
    }

    /** Hinweise auf einen schwachen oder überlasteten Access Point. */
    public function problems(): array
    {
        $p = [];
        if ($this->state === 'offline') {
            $p[] = 'offline';
        }
        if ($this->retries_pct !== null && $this->retries_pct >= 15) {
            $p[] = 'viele Funk-Wiederholungen ('.number_format($this->retries_pct, 1, ',', '').' %)';
        }
        if ($this->clients >= 35) {
            $p[] = "sehr viele Geräte ({$this->clients})";
        }
        if ($this->cpu_pct !== null && $this->cpu_pct >= 85) {
            $p[] = 'Prozessor ausgelastet';
        }

        return $p;
    }
}
