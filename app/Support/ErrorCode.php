<?php

namespace App\Support;

class ErrorCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function make(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'F-'.$code;
    }
}
