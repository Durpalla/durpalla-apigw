<?php

namespace App\Services\Search;

use App\Models\RouteProperty;
use App\Models\VehicleSchedule;

/**
 * Builds OpenSearch documents and valid boarding pair slugs
 * consistent with ScheduleRepository::searchTrip route rules.
 */
final class TripScheduleIndexDocument
{
    /**
     * @return list<string> lowercased "from|to" tokens
     */
    public static function pairSlugsForRoute(int $routeId, string $scheduleType): array
    {
        $rows = RouteProperty::query()
            ->where('route_id', $routeId)
            ->with('ghat')
            ->orderBy('serial_num')
            ->orderBy('id')
            ->get();

        $slugs = [];
        $n = $rows->count();
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }
                $rp1 = $rows[$i];
                $rp2 = $rows[$j];
                $ok = $scheduleType === 'reverse'
                    ? self::reverseOk($rp1, $rp2)
                    : self::straightOk($rp1, $rp2);
                if (! $ok) {
                    continue;
                }
                $n1 = self::ghatSlug($rp1);
                $n2 = self::ghatSlug($rp2);
                if ($n1 !== '' && $n2 !== '') {
                    $slugs[$n1.'|'.$n2] = true;
                }
            }
        }

        return array_keys($slugs);
    }

    private static function ghatSlug(RouteProperty $rp): string
    {
        $name = $rp->ghat->name ?? '';

        return strtolower(trim((string) $name));
    }

    private static function straightOk(RouteProperty $rp1, RouteProperty $rp2): bool
    {
        if ($rp1->serial_num < $rp2->serial_num) {
            return true;
        }

        return $rp1->serial_num == $rp2->serial_num && $rp1->id < $rp2->id;
    }

    private static function reverseOk(RouteProperty $rp1, RouteProperty $rp2): bool
    {
        if ($rp1->serial_num > $rp2->serial_num) {
            return true;
        }

        return $rp1->serial_num == $rp2->serial_num && $rp1->id > $rp2->id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromSchedule(VehicleSchedule $schedule): array
    {
        $schedule->loadMissing(['vehicle', 'route']);
        $vehicle = $schedule->vehicle;
        $route = $schedule->route;
        $vehicleType = $vehicle?->vehicle_type ?? '';
        $routeName = $route?->route_name ?? '';
        $vehicleName = $vehicle?->name ?? '';
        $pairSlugs = self::pairSlugsForRoute((int) $schedule->route_id, (string) $schedule->schedule_type);
        $searchable = strtolower($routeName.' '.$vehicleName.' '.implode(' ', $pairSlugs));

        return [
            'schedule_id' => (int) $schedule->id,
            'status' => strtolower((string) $schedule->status),
            'schedule_date' => $schedule->schedule_date?->format('Y-m-d') ?? date('Y-m-d', strtotime((string) $schedule->schedule_date)),
            'leaving_at' => $schedule->leaving_at ? date('c', strtotime((string) $schedule->leaving_at)) : null,
            'schedule_type' => (string) $schedule->schedule_type,
            'vehicle_type' => strtolower(trim((string) $vehicleType)),
            'route_name' => (string) $routeName,
            'vehicle_name' => (string) $vehicleName,
            'pair_slugs' => $pairSlugs,
            'searchable_text' => $searchable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function indexMapping(): array
    {
        return [
            'settings' => [
                'index' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
            'mappings' => [
                'properties' => [
                    'schedule_id' => ['type' => 'integer'],
                    'status' => ['type' => 'keyword'],
                    'schedule_date' => ['type' => 'date', 'format' => 'strict_date_optional_time||epoch_millis||yyyy-MM-dd'],
                    'leaving_at' => ['type' => 'date'],
                    'schedule_type' => ['type' => 'keyword'],
                    'vehicle_type' => ['type' => 'keyword'],
                    'route_name' => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                    'vehicle_name' => ['type' => 'text'],
                    'pair_slugs' => ['type' => 'keyword'],
                    'searchable_text' => ['type' => 'text'],
                ],
            ],
        ];
    }
}
