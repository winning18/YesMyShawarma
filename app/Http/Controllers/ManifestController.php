<?php

namespace App\Http\Controllers;

use App\Services\Settings\BusinessProfile;
use Illuminate\Http\JsonResponse;

/**
 * The home-screen app manifest, built from the Business profile so each
 * install shows its own name, colour and icon. Served at the old static
 * file's address, so phones that already installed the site keep working.
 */
class ManifestController extends Controller
{
    public function __invoke(BusinessProfile $profile): JsonResponse
    {
        $icon = $profile->iconUrl();

        return response()->json(array_filter([
            'name' => $profile->name(),
            'short_name' => $profile->name(),
            'description' => $profile->tagline(),
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#000000',
            'theme_color' => $profile->colours()['brand_primary'],
            'icons' => $icon ? [
                ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png'],
            ] : null,
        ]), 200, ['Content-Type' => 'application/manifest+json']);
    }
}
