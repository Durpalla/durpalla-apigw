<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Customer web/app and agent app listing rules.
 * Merchants can still operate unapproved inventory in their own dashboards.
 */
final class PublicListingVisibility
{
    public static function applyApprovedVehicle(Builder $scheduleQuery, string $vehicleRelation = 'vehicle'): void
    {
        if (! Schema::hasColumn('vehicles', 'is_approved')) {
            return;
        }

        $scheduleQuery->whereHas($vehicleRelation, static function (Builder $q) {
            $q->where('is_approved', true);
        });
    }

    public static function applyApprovedHotel(Builder $hotelQuery, string $table = 'hotels'): void
    {
        if (! Schema::hasColumn($table, 'is_approved')) {
            return;
        }

        $hotelQuery->where("{$table}.is_approved", true);
    }
}
