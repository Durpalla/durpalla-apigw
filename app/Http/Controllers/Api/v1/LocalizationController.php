<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\LocalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalizationController extends Controller
{
    public function __construct(
        private readonly LocalizationService $localizationService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->localizationService->metadata(),
        ]);
    }

    public function show(Request $request, string $locale): JsonResponse
    {
        try {
            $payload = $this->localizationService->cachedDictionary($locale);
        } catch (NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => __('Locale not supported.'),
            ], 404);
        }

        $etag = $payload['etag'];
        unset($payload['etag']);

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ])->withHeaders([
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
