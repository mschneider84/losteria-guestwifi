<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Unifi\UnifiClient;
use App\Services\Unifi\UnifiManager;
use App\Services\Unifi\UnifiOsClient;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UnifiClient::class, fn () => Settings::get('unifi.mode') === 'local'
            ? UnifiOsClient::fromSettings()
            : \App\Services\Unifi\UnifiCloudClient::fromSettings());
        $this->app->singleton(UnifiManager::class);
    }

    public function boot(): void
    {
        Settings::applyToConfig();
        Paginator::defaultView('admin.partials.pagination');

        // Rechte nur über den Katalog, nie über Rollennamen
        Gate::before(fn (User $user, string $ability) => $user->hasPerm($ability) ?: null);

        RateLimiter::for('portal', fn (Request $r) => Limit::perMinute(12)->by($r->ip()));
        RateLimiter::for('meldungen', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
    }
}
