<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['unifi_api_key'];

    protected function casts(): array
    {
        return [
            'headline' => 'array',
            'welcome_text' => 'array',
            'extra_texts' => 'array',
            'use_default_limits' => 'boolean',
            'collect_email' => 'boolean',
            'newsletter_enabled' => 'boolean',
            'active' => 'boolean',
            'is_demo' => 'boolean',
            'unifi_api_key' => 'encrypted',
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

    /** Text der Gästeseite: eigener Text des Standorts → Standardtext für alle Restaurants → eingebauter Text. */
    public function guestText(string $field, string $lang): string
    {
        return ($this->ownText($field)[$lang] ?? null)
            ?: (\App\Support\GuestDefaults::text($field, $lang) ?: trans('portal.'.$field, [], $lang));
    }

    /** @return array<string, string> Sprache => eigener Text */
    public function ownText(string $field): array
    {
        return (array) match ($field) {
            'headline' => $this->headline,
            'welcome' => $this->welcome_text,
            default => $this->extra_texts[$field] ?? [],
        };
    }

    /** Eigenes Logo des Standorts, sonst das Standard-Logo aus „Gästeseite & Rechtstexte“. */
    public function logoUrl(): ?string
    {
        if ($this->logo_path) {
            return route('media.logo', [$this->slug, 'v' => substr(md5($this->logo_path), 0, 8)]);
        }
        $default = \App\Support\Settings::get('brand.logo_path');

        return $default ? route('media.default-logo', ['v' => substr(md5($default), 0, 8)]) : null;
    }

    /** Newsletter nur, wenn das Modul eingeschaltet ist und der Standort ihn anbietet. */
    public function newsletterActive(): bool
    {
        return (bool) \App\Support\Settings::get('modules.newsletter') && $this->newsletter_enabled;
    }

    public function sessionLabel(): string
    {
        $h = intdiv($this->session_minutes, 60);
        $m = $this->session_minutes % 60;

        return $m ? sprintf('%d:%02d h', $h, $m) : $h.' h';
    }
}
