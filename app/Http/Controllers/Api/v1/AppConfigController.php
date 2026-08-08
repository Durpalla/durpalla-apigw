<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $platform = strtolower((string) $request->query('platform', 'android'));
        if (! in_array($platform, ['android', 'ios'], true)) {
            $platform = 'android';
        }

        $platformConfig = config("app_mobile.{$platform}", []);

        return response()->json([
            'success' => true,
            'data' => [
                'version' => [
                    'min' => (string) ($platformConfig['min_version'] ?? '1.0.0'),
                    'latest' => (string) ($platformConfig['latest_version'] ?? '1.0.0'),
                    'force_update' => (bool) ($platformConfig['force_update'] ?? false),
                    'store_url' => $platformConfig['store_url'] ?? null,
                ],
                'sections' => [
                    'my_offers' => (bool) config('app_mobile.sections.my_offers', true),
                    'my_trips' => (bool) config('app_mobile.sections.my_trips', true),
                    'upcoming_trips' => (bool) config('app_mobile.sections.upcoming_trips', true),
                    'gallery_slider' => (bool) config('app_mobile.sections.gallery_slider', true),
                ],
            ],
        ]);
    }
}
