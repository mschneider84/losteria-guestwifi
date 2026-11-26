<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Netzwerk-Auswertungen (Bereiche, Auslastung, Verweildauer, Stammgäste, Technik).
 * Keine MAC-Adressen von Gästen im Klartext: Geräte nur als HMAC (device_hash).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Access Points je Standort mit Bereich (Innen, Terrasse, Bar …)
        Schema::create('access_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('unifi_id', 64);
            $table->string('mac', 17)->nullable(); // MAC des Access Points (Gerät des Restaurants, kein Gast)
            $table->string('name', 120)->nullable();
            $table->string('model', 60)->nullable();
            $table->string('area', 40)->nullable();
            $table->string('state', 30)->nullable();
            $table->unsignedSmallInteger('clients')->default(0);
            $table->unsignedSmallInteger('guests')->default(0);
            $table->decimal('retries_pct', 5, 1)->nullable();
            $table->decimal('cpu_pct', 5, 1)->nullable();
            $table->unsignedInteger('uptime_sec')->nullable();
            $table->unsignedSmallInteger('peak_clients')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['location_id', 'unifi_id']);
        });

        // Zählstände alle 5 Minuten je Bereich (nur Anzahlen, keine Personendaten)
        Schema::create('network_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('area', 40); // „gesamt“ für den ganzen Standort
            $table->timestamp('taken_at');
            $table->unsignedSmallInteger('clients')->default(0);
            $table->unsignedSmallInteger('guests')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->index(['location_id', 'taken_at']);
            $table->index(['is_demo', 'taken_at']);
        });

        // Besuche von Gastgeräten (pseudonym) – für Verweildauer, Wiederkehr, Datenverbrauch
        Schema::create('client_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->string('area', 40)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->string('device_type', 20)->nullable();
            $table->unsignedInteger('rx_mb')->nullable();
            $table->unsignedInteger('tx_mb')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->index(['location_id', 'first_seen_at']);
            $table->index(['device_hash', 'last_seen_at']);
        });

        // Klick auf eine Aktion je Gerät (pseudonym) – für „Wirkung von Aktionen“
        Schema::create('promotion_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->timestamp('clicked_at');
            $table->boolean('is_demo')->default(false);
            $table->index(['promotion_id', 'clicked_at']);
        });

        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->string('device_type', 20)->nullable()->after('ssid'); // grob: iPhone, Android, Windows …
        });
    }

    public function down(): void
    {
        Schema::table('guest_sessions', fn (Blueprint $table) => $table->dropColumn('device_type'));
        Schema::dropIfExists('promotion_clicks');
        Schema::dropIfExists('client_visits');
        Schema::dropIfExists('network_snapshots');
        Schema::dropIfExists('access_points');
    }
};
