<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Zugangsschlüssel für die Dashboard-Schnittstelle. Gespeichert wird nur der Hash. */
class ApiKey extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['can_write' => 'boolean', 'expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /** @return array{0: ApiKey, 1: string} Modell und Klartext-Schlüssel (nur einmal sichtbar) */
    public static function generate(string $name, bool $canWrite, ?\DateTimeInterface $expiresAt = null, ?int $userId = null): array
    {
        $plain = 'gw_'.Str::random(40);
        $key = self::create([
            'name' => $name,
            'prefix' => substr($plain, 0, 10),
            'key_hash' => hash('sha256', $plain),
            'can_write' => $canWrite,
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        $key = self::where('key_hash', hash('sha256', $plain))->first();

        return $key && (! $key->expires_at || $key->expires_at->isFuture()) ? $key : null;
    }
}
