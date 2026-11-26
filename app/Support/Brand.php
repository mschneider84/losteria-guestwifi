<?php

namespace App\Support;

class Brand
{
    public static function name(): string
    {
        return (string) Settings::get('brand.name');
    }

    public static function color(): string
    {
        return (string) Settings::get('brand.color');
    }

    public static function copyright(): string
    {
        return '© '.date('Y').' '.Settings::get('brand.company');
    }

    /** Platzhalter-Logo, solange keines hochgeladen ist. */
    public static function logoUrl(): ?string
    {
        $path = Settings::get('brand.logo');

        return $path ? asset('storage/'.$path) : null;
    }
}
