<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rechtstexte der Gästeseite (Nutzungsbedingungen, Datenschutz, Impressum) in DE/EN/IT, im Portal bearbeitbar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_texts', function (Blueprint $table) {
            $table->string('key', 20)->primary(); // terms | privacy | imprint
            $table->json('content'); // {"de": "...", "en": "...", "it": "..."} – einfaches Markdown
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_texts');
    }
};
