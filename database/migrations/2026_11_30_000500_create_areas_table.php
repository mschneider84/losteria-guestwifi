<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Bereiche (Innen, Terrasse …) als eigene Liste für alle Restaurants statt Komma-Feld in den Einstellungen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('color', 7)->default('#888888');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $palette = ['Innen' => '#2a5bb8', 'Terrasse' => '#1f8a4c', 'Bar' => '#c77700', 'Eingang' => '#7b4bb8'];
        $extra = ['#c2185b', '#00838f', '#6d4c41', '#558b2f', '#5e35b1', '#ef6c00'];
        $setting = (string) DB::table('settings')->where('key', 'network.areas')->value('value');
        $names = array_map('trim', explode(',', $setting ?: 'Innen,Terrasse,Bar,Eingang'));
        $names = array_merge($names, DB::table('access_points')->whereNotNull('area')->distinct()->pluck('area')->all());
        $i = 0;
        foreach (array_values(array_unique(array_filter($names, fn ($n) => $n !== '' && $n !== 'gesamt'))) as $sort => $name) {
            DB::table('areas')->insert([
                'name' => mb_substr($name, 0, 40), 'color' => $palette[$name] ?? $extra[$i++ % count($extra)],
                'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('settings')->where('key', 'network.areas')->delete();
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
