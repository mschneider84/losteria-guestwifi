<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Bereich eines Restaurants (Innen, Terrasse, Bar …) – eine Liste für alle Standorte, den Access Points zugeordnet. */
class Area extends Model
{
    protected $guarded = ['id'];

    public const PALETTE = ['#2a5bb8', '#1f8a4c', '#c77700', '#7b4bb8', '#c2185b', '#00838f', '#6d4c41', '#558b2f', '#5e35b1', '#ef6c00'];

    /** @return list<string> */
    public static function names(): array
    {
        return self::orderBy('sort')->orderBy('name')->pluck('name')->all();
    }

    /** @return array<string, string> Name => Farbe */
    public static function colors(): array
    {
        return self::pluck('color', 'name')->all();
    }

    public static function nextColor(): string
    {
        $used = self::pluck('color')->all();

        return collect(self::PALETTE)->first(fn ($c) => ! in_array($c, $used, true)) ?? self::PALETTE[self::count() % count(self::PALETTE)];
    }

    /** Umbenennen – auch an Access Points und in bisherigen Auswertungen, damit die Zahlen zusammenbleiben. */
    public function rename(string $name): void
    {
        $old = $this->name;
        if ($old === $name) {
            return;
        }
        DB::transaction(function () use ($old, $name) {
            $this->update(['name' => $name]);
            foreach (['access_points', 'network_snapshots', 'client_visits'] as $table) {
                DB::table($table)->where('area', $old)->update(['area' => $name]);
            }
        });
    }

    /** Löschen: Access Points verlieren die Zuordnung, bisherige Auswertungen bleiben unter dem alten Namen. */
    public function remove(): void
    {
        DB::table('access_points')->where('area', $this->name)->update(['area' => null]);
        $this->delete();
    }
}
