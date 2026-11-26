<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

// Gästeseite – UniFi leitet hierher weiter (External Portal)
Route::get('/guest/s/{site}', [PortalController::class, 'landing'])->name('portal.landing');
Route::get('/guest/s/{site}/', [PortalController::class, 'landing']);
Route::get('/wlan/{location:slug}', [PortalController::class, 'show'])->name('portal.show');
Route::get('/wlan/{location:slug}/vorschau', [PortalController::class, 'preview'])->name('portal.preview');
Route::post('/wlan/{location:slug}/verbinden', [PortalController::class, 'connect'])->middleware('throttle:portal')->name('portal.connect');
Route::get('/wlan/{location:slug}/online', [PortalController::class, 'done'])->name('portal.done');
Route::get('/newsletter/bestaetigen/{token}', [PortalController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/abmelden/{token}', [PortalController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// Bilder (Logo, Aktionen) – immer über die Portal-Domain, damit sie im Walled Garden laden
Route::get('/bild/logo/{location:slug}', [\App\Http\Controllers\MediaController::class, 'logo'])->name('media.logo');
Route::get('/bild/aktion/{promotion}', [\App\Http\Controllers\MediaController::class, 'promotion'])->name('media.promotion');
Route::get('/wlan/{location:slug}/aktion/{promotion}', [PortalController::class, 'promotionClick'])->name('portal.promotion');

// Verwaltung
Route::get('/', fn () => redirect()->route('admin.dashboard'));
Route::get('/anmelden', [AuthController::class, 'showLogin'])->name('login');
Route::post('/anmelden', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
Route::post('/setup', [AuthController::class, 'setup']);

Route::middleware(['auth', \App\Http\Middleware\AdminLocale::class])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/abmelden', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    Route::middleware('can:locations.manage')->group(function () {
        Route::get('/standorte', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/standorte/neu', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/standorte', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/standorte/{location}', [LocationController::class, 'edit'])->name('locations.edit');
        Route::put('/standorte/{location}', [LocationController::class, 'update'])->name('locations.update');
    });

    Route::get('/aktionen', [\App\Http\Controllers\Admin\IntegrationController::class, 'promotions'])->middleware('can:dashboard.view')->name('promotions.index');
    Route::get('/kontakte', [ContactController::class, 'index'])->middleware('can:contacts.view')->name('contacts.index');
    Route::get('/kontakte/export', [ContactController::class, 'export'])->middleware('can:contacts.export')->name('contacts.export');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/einstellungen', [SettingsController::class, 'index'])->name('settings');
        Route::put('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/einstellungen/schnittstelle', [\App\Http\Controllers\Admin\IntegrationController::class, 'keys'])->name('api-keys.index');
        Route::post('/einstellungen/schnittstelle', [\App\Http\Controllers\Admin\IntegrationController::class, 'storeKey'])->name('api-keys.store');
        Route::delete('/einstellungen/schnittstelle/{key}', [\App\Http\Controllers\Admin\IntegrationController::class, 'destroyKey'])->name('api-keys.destroy');
        Route::post('/einstellungen/unifi-konsolen', [SettingsController::class, 'loadConsoles'])->name('settings.unifi-consoles');
        Route::post('/einstellungen/unifi-test', [SettingsController::class, 'testUnifi'])->name('settings.unifi-test');
    });

    Route::middleware('can:users.manage')->group(function () {
        Route::get('/benutzer', [UserController::class, 'index'])->name('users.index');
        Route::post('/benutzer', [UserController::class, 'store'])->name('users.store');
        Route::put('/benutzer/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::get('/systemstatus', [SystemController::class, 'status'])->middleware('can:status.view')->name('status');
    Route::get('/neuigkeiten', [SystemController::class, 'news'])->name('news');
    Route::post('/demo', [SystemController::class, 'toggleDemo'])->middleware('can:demo')->name('demo');
    Route::post('/meldungen', [SystemController::class, 'storeMeldung'])->middleware('throttle:meldungen')->name('meldungen.store');
    Route::get('/meldungen', [SystemController::class, 'meldungen'])->middleware('can:meldungen.manage')->name('meldungen.index');
    Route::put('/meldungen/{meldung}', [SystemController::class, 'meldungStatus'])->middleware('can:meldungen.manage')->name('meldungen.status');
});
