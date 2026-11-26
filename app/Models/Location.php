<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'headline' => 'array',
            'welcome_text' => 'array',
            'collect_email' => 'boolean',
            'newsletter_enabled' => 'boolean',
            'active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GuestSession::class);
    }

    public function scopeMode(Builder $q, bool $demo): Builder
    {
        return $q->where('is_demo', $demo);
    }

    /** Eigener Text in der Sprache des Gastes, sonst der Standardtext dieser Sprache. */
    public function text(string $field, string $lang, string $fallback): string
    {
        $values = $this->{$field} ?? [];

        return $values[$lang] ?? $fallback;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('media.logo', [$this->slug, 'v' => substr(md5($this->logo_path), 0, 8)]) : null;
    }

    public function sessionLabel(): string
    {
        $h = intdiv($this->session_minutes, 60);
        $m = $this->session_minutes % 60;

        return $m ? sprintf('%d:%02d h', $h, $m) : $h.' h';
    }
}
