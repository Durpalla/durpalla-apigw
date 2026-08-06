<?php

namespace App\Support;

use App\Models\Ghat;
use Illuminate\Support\Facades\Schema;

/**
 * Ghat names are often stored as "City (Boarding Point)" e.g. "Dhaka (Sadar Ghat)".
 * Search From/To should use the city key; boarding points are chosen at checkout.
 */
final class GhatPlaceName
{
    /**
     * Extract the city / place used for trip_from / trip_to search.
     * "Dhaka (Sadar Ghat)" → "Dhaka"; "Sadar Ghat" → "Sadar Ghat".
     */
    public static function cityKey(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (preg_match('/^(.+?)\s*\((.+)\)\s*$/u', $name, $m) === 1) {
            $city = trim($m[1]);
            if ($city !== '') {
                return $city;
            }
        }

        return $name;
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(self::cityKey($name), 'UTF-8');
    }

    /**
     * Whether a stored ghat name matches a user/search place string.
     */
    public static function matches(string $storedName, string $queryName): bool
    {
        $stored = trim($storedName);
        $query = trim($queryName);
        if ($stored === '' || $query === '') {
            return false;
        }

        if (strcasecmp($stored, $query) === 0) {
            return true;
        }

        return self::normalize($stored) === self::normalize($query);
    }

    /**
     * SQL predicate: column matches place (exact or city-key).
     * Prefer {@see resolveGhatIds()} + ghat_id IN (...) on the hot path.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function sqlMatch(string $column, string $place): array
    {
        $place = trim($place);
        $city = self::cityKey($place);

        // Prefix LIKE keeps ghats.name index usable for the city branch.
        $sql = "(
            {$column} = ?
            OR {$column} LIKE CONCAT(?, ' (%')
        )";

        return [$sql, [$place, $city]];
    }

    /**
     * Resolve a search place string to ghat ids (index-friendly trip EXISTS).
     *
     * @return list<int>
     */
    public static function resolveGhatIds(string $place): array
    {
        $place = trim($place);
        if ($place === '' || ! Schema::hasTable('ghats')) {
            return [];
        }

        $city = self::cityKey($place);

        return Ghat::query()
            ->where(function ($q) use ($place, $city) {
                $q->where('name', $place)
                    ->orWhere('name', 'like', $city.' (%');
            })
            ->limit(50)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Slugs for OpenSearch pair matching: full name + city key.
     *
     * @return list<string>
     */
    public static function searchSlugs(string $name): array
    {
        $full = mb_strtolower(trim($name), 'UTF-8');
        $city = self::normalize($name);
        $out = [];
        if ($full !== '') {
            $out[$full] = true;
        }
        if ($city !== '') {
            $out[$city] = true;
        }

        return array_keys($out);
    }
}
