<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Promotion;
use App\Support\Media;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function logo(Location $location): Response
    {
        return $this->serve($location->logo_path);
    }

    public function defaultLogo(): Response
    {
        return $this->serve(\App\Support\Settings::get('brand.logo_path'));
    }

    public function promotion(Promotion $promotion): Response
    {
        return $this->serve($promotion->image_path);
    }

    private function serve(?string $path): Response
    {
        abort_unless($path && Media::disk()->exists($path), 404);

        return response(Media::disk()->get($path), 200, [
            'Content-Type' => Media::disk()->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
