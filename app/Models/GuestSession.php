<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Freischaltung. Die MAC-Adresse wird nie im Klartext gespeichert,
 * nur als HMAC (device_hash), damit Wiederkehrer zählbar sind.
 */
class GuestSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_returning' => 'boolean',
            'is_demo' => 'boolean',
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public static function hashDevice(string $mac): string
    {
        return hash_hmac('sha256', strtolower(trim($mac)), (string) config('app.key'));
    }
}
