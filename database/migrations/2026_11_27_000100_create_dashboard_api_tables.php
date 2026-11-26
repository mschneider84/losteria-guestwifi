<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix', 12);
            $table->string('key_hash', 64)->unique();
            $table->boolean('can_write')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Marketingaktionen – Inhalt kommt vom L'Osteria Dashboard
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100);
            $table->json('title');
            $table->json('text')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image_source_url')->nullable();
            $table->string('image_path')->nullable();
            $table->json('location_slugs')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('weekdays', 20)->nullable();
            $table->string('time_from', 5)->nullable();
            $table->string('time_to', 5)->nullable();
            $table->string('placement', 10)->default('both');
            $table->smallInteger('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['external_id', 'is_demo']);
            $table->index(['active', 'is_demo', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['promotion_id', 'location_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_stats');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('api_keys');
    }
};
