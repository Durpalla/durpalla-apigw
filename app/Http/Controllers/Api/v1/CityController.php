<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CityController extends Controller
{
    /**
     * GET /api/v1/cities and /api/v1/meta/cities
     *
     * Query:
     * - per_page: int (1..1000) limit
     * - search: string filter by name/code/state/country
     * - active_only: bool (default true) when column exists
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'search' => ['nullable', 'string', 'max:191'],
            'active_only' => ['nullable'],
        ]);

        if (! Schema::hasTable('cities')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $limit = (int) ($validated['per_page'] ?? 500);
        $search = trim((string) ($validated['search'] ?? ''));

        $activeOnly = true;
        if ($request->has('active_only')) {
            $v = $validated['active_only'] ?? null;
            $activeOnly = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $activeOnly = $activeOnly === null ? true : $activeOnly;
        }

        $q = DB::table('cities')
            ->select(
                'id',
                'country_id',
                'name',
                'code',
                'country',
                'state',
                'latitude',
                'longitude',
                'is_active',
                'created_at',
                'updated_at',
            )
            ->orderBy('name');

        if ($activeOnly && Schema::hasColumn('cities', 'is_active')) {
            $q->where('is_active', 1);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($qq) use ($like) {
                $qq->where('name', 'LIKE', $like)
                    ->orWhere('code', 'LIKE', $like)
                    ->orWhere('state', 'LIKE', $like)
                    ->orWhere('country', 'LIKE', $like);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $q->limit($limit)->get(),
        ]);
    }
}

