<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Markenrot wie auf losteria.net: alte Standardfarbe #b3261e durch #db052c ersetzen (selbst gewählte Farben bleiben). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('locations')->whereRaw('lower(brand_color) = ?', ['#b3261e'])->update(['brand_color' => '#db052c']);
        DB::table('settings')->where('key', 'brand.color')->where('value', '#b3261e')->update(['value' => '#db052c']);
        Cache::forget('settings.all');
    }

    public function down(): void {}
};
