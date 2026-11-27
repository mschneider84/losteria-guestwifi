<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Standort kann die Standardwerte der Gästeseite übernehmen; weitere eigene Texte (Knopf, Seite nach dem Verbinden). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('use_default_limits')->default(false)->after('quota_mb'); // bestehende Standorte behalten ihre Werte
            $table->json('extra_texts')->nullable()->after('welcome_text');
        });
        Schema::table('locations', fn (Blueprint $t) => $t->string('brand_color', 7)->default('#db052c')->change());
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['use_default_limits', 'extra_texts']);
        });
    }
};
