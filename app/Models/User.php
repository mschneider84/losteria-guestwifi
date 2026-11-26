<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['active' => true, 'role' => 'viewer', 'locale' => 'de'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function hasPerm(string $perm): bool
    {
        return $this->active && in_array($perm, Permissions::forRole($this->role), true);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
