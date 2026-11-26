<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\Http;
use App\Support\Media;
use Illuminate\Support\Str;
use Throwable;

/**
 * Übernimmt eine Aktion vom Dashboard. Das Bild wird heruntergeladen und vom Portal selbst
 * ausgeliefert – vor der Freischaltung erreichen Gäste nur das Portal (Walled Garden).
 */
class PromotionImporter
{
    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function upsert(string $externalId, array $data): Promotion
    {
        $promotion = Promotion::firstOrNew(['external_id' => $externalId, 'is_demo' => false]);
        $imageUrl = $data['bild_url'] ?? null;

        $promotion->fill([
            'title' => $data['titel'],
            'text' => $data['text'] ?? null,
            'cta_label' => $data['button_text'] ?? null,
            'cta_url' => $data['button_url'] ?? null,
            'location_slugs' => $data['standorte'] ?? null ?: null,
            'starts_at' => $data['start'] ?? null,
            'ends_at' => $data['ende'] ?? null,
            'weekdays' => isset($data['wochentage']) ? implode(',', $data['wochentage']) : null,
            'time_from' => $data['uhrzeit_von'] ?? null,
            'time_to' => $data['uhrzeit_bis'] ?? null,
            'placement' => $data['platzierung'] ?? 'both',
            'priority' => $data['prioritaet'] ?? 0,
            'active' => $data['aktiv'] ?? true,
        ]);

        if (! $imageUrl) {
            $this->deleteImage($promotion);
            $promotion->image_source_url = null;
        } elseif ($imageUrl !== $promotion->image_source_url || ! $promotion->image_path) {
            $this->deleteImage($promotion);
            $promotion->image_path = $this->download($imageUrl);
            $promotion->image_source_url = $imageUrl;
        }
        $promotion->save();

        return $promotion;
    }

    public function delete(Promotion $promotion): void
    {
        $this->deleteImage($promotion);
        $promotion->delete();
    }

    private function download(string $url): string
    {
        try {
            $response = Http::timeout(10)->withOptions(['stream' => false])->get($url);
        } catch (Throwable $e) {
            throw new \InvalidArgumentException('Bild konnte nicht geladen werden: '.$e->getMessage());
        }
        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($response->failed() || ! isset(self::MIME[$mime])) {
            throw new \InvalidArgumentException("Bild konnte nicht geladen werden (HTTP {$response->status()}, {$mime}). Erlaubt: JPEG, PNG, WebP.");
        }
        $body = $response->body();
        if (strlen($body) > 3 * 1024 * 1024) {
            throw new \InvalidArgumentException('Bild ist größer als 3 MB.');
        }
        if (@getimagesizefromstring($body) === false) {
            throw new \InvalidArgumentException('Die Datei ist kein gültiges Bild.');
        }
        $path = 'aktionen/'.Str::random(24).'.'.self::MIME[$mime];
        Media::disk()->put($path, $body);

        return $path;
    }

    private function deleteImage(Promotion $promotion): void
    {
        if ($promotion->image_path) {
            Media::disk()->delete($promotion->image_path);
            $promotion->image_path = null;
        }
    }
}
