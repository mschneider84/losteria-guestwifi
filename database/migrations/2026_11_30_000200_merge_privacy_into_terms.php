<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Nutzungsbedingungen und Datenschutz sind ein gemeinsamer Text: vorhandenen Datenschutz-Text an die Nutzungsbedingungen anhängen. */
return new class extends Migration
{
    public function up(): void
    {
        $privacy = DB::table('legal_texts')->where('key', 'privacy')->first();
        if ($privacy) {
            $terms = DB::table('legal_texts')->where('key', 'terms')->first();
            $t = $terms ? (array) json_decode($terms->content, true) : [];
            foreach ((array) json_decode($privacy->content, true) as $lang => $text) {
                $t[$lang] = trim(($t[$lang] ?? '')."\n\n".$text);
            }
            DB::table('legal_texts')->updateOrInsert(['key' => 'terms'], [
                'content' => json_encode(array_filter($t), JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => $terms->created_at ?? now(),
            ]);
            DB::table('legal_texts')->where('key', 'privacy')->delete();
        }
        DB::table('settings')->where('key', 'portal.privacy_url')->delete();
        Cache::forget('legal.texts');
        Cache::forget('settings.all');
    }

    public function down(): void {}
};
