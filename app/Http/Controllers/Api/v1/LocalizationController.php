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

    public function appIndex(string $app): JsonResponse
    {
        try {
            $data = $this->localizationService->metadata($app);
        } catch (NotFoundHttpException) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function showApp(Request $request, string $app, string $locale): JsonResponse
    {
        $combined = $request->boolean('combined');

        try {
            $payload = $this->localizationService->cachedDictionary($app, $locale, null, $combined);
        } catch (NotFoundHttpException) {
            return $this->notFoundResponse();
        }

        return $this->jsonDictionary($request, $payload);
    }

    public function showNamespace(Request $request, string $app, string $locale, string $namespace): JsonResponse
    {
        try {
            $payload = $this->localizationService->cachedDictionary($app, $locale, $namespace);
        } catch (NotFoundHttpException) {
            return $this->notFoundResponse();
        }

        return $this->jsonDictionary($request, $payload);
    }

    /** @deprecated Use /localizations/web-customer/{locale}?combined=1 */
    public function legacyShow(Request $request, string $locale): JsonResponse
    {
        $app = (string) config('localization.legacy_default_app', 'web-customer');

        try {
            $payload = $this->localizationService->cachedDictionary($app, $locale, null, true);
        } catch (NotFoundHttpException) {
            return $this->notFoundResponse();
        }

        return $this->jsonDictionary($request, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonDictionary(Request $request, array $payload): JsonResponse
    {
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

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Locale not supported.'),
        ], 404);
    }
}
