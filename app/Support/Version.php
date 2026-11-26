<?php

namespace App\Support;

use Carbon\Carbon;

/** Version = oberster Eintrag in config/changelog.php. */
class Version
{
    public static function top(): array
    {
        return config('changelog')[0] ?? ['version' => '0.0.0', 'build' => 0, 'date' => now()->toDateTimeString()];
    }

    public static function version(): string
    {
        return self::top()['version'];
    }

    public static function build(): int
    {
        return (int) self::top()['build'];
    }

    public static function date(): string
    {
        return Carbon::parse(self::top()['date'])->format('d.m.Y');
    }

    public static function label(): string
    {
        return __('Version :v · Build :b · Stand :d', ['v' => self::version(), 'b' => self::build(), 'd' => self::date()]);
    }
}
