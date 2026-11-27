<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jeder Standort kann eine eigene UniFi-Konsole (UDM SE) und optional einen eigenen API-Schlüssel haben. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('unifi_console_id', 120)->nullable()->after('unifi_site');
            $table->text('unifi_api_key')->nullable()->after('unifi_console_id'); // verschlüsselt (Cast „encrypted“)
            $table->string('unifi_wan_ip', 45)->nullable()->after('unifi_api_key'); // öffentliche IP der UDM, um Gäste dem Standort zuzuordnen
        });
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn(['unifi_console_id', 'unifi_api_key', 'unifi_wan_ip']));
    }
};
