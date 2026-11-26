<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Speicherort für hochgeladene Bilder: der Bucket von Laravel Cloud (Disk s3), lokal "public".
 * Ausgeliefert wird immer über das Portal selbst, damit Gäste die Bilder schon vor der
 * Freischaltung sehen (nur die Portal-Domain steht im Walled Garden).
 */
class Media
{
    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function diskName(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }
}
