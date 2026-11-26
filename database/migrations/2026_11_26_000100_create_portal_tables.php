<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('unifi_site')->default('default');
            $table->string('city')->nullable();
            $table->string('brand_color', 7)->default('#b3261e');
            $table->string('logo_path')->nullable();
            $table->string('background_path')->nullable();
            $table->json('headline')->nullable();
            $table->json('welcome_text')->nullable();
            $table->unsignedInteger('session_minutes')->default(240);
            $table->unsignedInteger('down_kbps')->nullable();
            $table->unsignedInteger('up_kbps')->nullable();
            $table->unsignedInteger('quota_mb')->nullable();
            $table->string('redirect_url')->nullable();
            $table->boolean('collect_email')->default(true);
            $table->boolean('newsletter_enabled')->default(true);
            $table->boolean('active')->default(true);
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['unifi_site', 'is_demo']);
        });

        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('device_hash', 64)->index();
            $table->string('ap_mac', 17)->nullable();
            $table->string('ssid')->nullable();
            $table->string('language', 2)->default('de');
            $table->string('method', 10)->default('click');
            $table->boolean('is_returning')->default(false);
            $table->string('status', 12)->default('authorized');
            $table->string('error')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['location_id', 'created_at']);
            $table->index(['is_demo', 'created_at']);
        });

        Schema::create('newsletter_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('language', 2)->default('de');
            $table->string('token', 64)->unique();
            $table->string('status', 12)->default('pending')->index();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip_hash', 64)->nullable();
            $table->string('consent_text_version', 20)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->unique(['email', 'is_demo']);
        });

        Schema::create('meldungen', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('page_url')->nullable();
            $table->string('page_title')->nullable();
            $table->string('app_version', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('new')->index();
            $table->text('reply')->nullable();
            $table->string('done_in_version', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10);
            $table->string('code', 10)->nullable()->index();
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_log', 'system_logs', 'meldungen', 'newsletter_contacts', 'guest_sessions', 'locations', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
