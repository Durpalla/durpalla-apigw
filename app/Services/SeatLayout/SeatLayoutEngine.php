<?php

namespace App\Services\SeatLayout;

use App\Models\SeatLayout\SeatLayout;
use App\Models\SeatLayout\Seat;
use App\Models\SeatLayout\TripSeatInventory;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Support\Facades\DB;

class SeatLayoutEngine
{
    /**
     * Get layout for a vehicle/schedule.
     */
    public function getLayoutForSchedule(VehicleSchedule $schedule): array
    {
        $vehicle = $schedule->vehicle;
        
        if ($vehicle->source === 'local') {
            return $this->getLocalLayout($schedule);
        } else {
            return $this->getSupplierLayout($schedule);
        }
    }

    /**
     * Get local layout with availability.
     */
    protected function getLocalLayout(VehicleSchedule $schedule): array
    {
        $vehicle = $schedule->vehicle;
        // Get seats assigned to this vehicle or from a layout matching vehicle type
        $seats = Seat::where(function ($query) use ($vehicle) {
            $query->where('vehicle_id', $vehicle->id)
                  ->orWhereHas('seatLayout', function ($q) use ($vehicle) {
                      $q->where('vehicle_type', $vehicle->vehicle_type);
                  });
        })
        ->where('is_active', true)
        ->orderBy('row_number')
        ->orderBy('column_number')
        ->get();

        $inventory = TripSeatInventory::where('schedule_id', $schedule->id)
            ->get()
            ->keyBy('seat_id');

        $layout = [];
        foreach ($seats as $seat) {
            $inventoryItem = $inventory->get($seat->id);
            
            $layout[] = [
                'id' => $seat->id,
                'seat_number' => $seat->seat_number,
                'row' => $seat->row_number,
                'column' => $seat->column_number,
                'type' => $seat->seat_type,
                'is_window' => $seat->is_window,
                'is_aisle' => $seat->is_aisle,
                'is_emergency_exit' => $seat->is_emergency_exit,
                'is_disabled_accessible' => $seat->is_disabled_accessible,
                'gender_rule' => $seat->gender_rule,
                'price' => $this->getSeatPrice($seat, $schedule),
                'status' => $inventoryItem ? $inventoryItem->status : 'available',
                'passenger_gender' => $inventoryItem->passenger_gender ?? null,
                'locked_until' => $inventoryItem && $inventoryItem->locked_until ? $inventoryItem->locked_until->toDateTimeString() : null,
            ];
        }

        return $this->organizeByRows($layout);
    }

    /**
     * Get supplier layout (fetched from API or cached).
     */
    protected function getSupplierLayout(VehicleSchedule $schedule): array
    {
        // This would fetch from supplier API or use cached data
        // For now, return empty - to be implemented based on supplier API
        return [];
    }

    /**
     * Initialize inventory for a schedule.
     */
    public function initializeInventory(VehicleSchedule $schedule): void
    {
        $vehicle = $schedule->vehicle;
        
        if ($vehicle->source !== 'local') {
            return; // Supplier schedules handled separately
        }

        // Get seats assigned to this vehicle or from a layout matching vehicle type
        $seats = Seat::where(function ($query) use ($vehicle) {
            $query->where('vehicle_id', $vehicle->id)
                  ->orWhereHas('seatLayout', function ($q) use ($vehicle) {
                      $q->where('vehicle_type', $vehicle->vehicle_type);
                  });
        })
        ->where('is_active', true)
        ->get();

        DB::transaction(function () use ($schedule, $seats) {
            foreach ($seats as $seat) {
                TripSeatInventory::firstOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'seat_id' => $seat->id,
                    ],
                    [
                        'status' => 'available',
                    ]
                );
            }
        });
    }

    /**
     * Get seat price for a schedule.
     */
    protected function getSeatPrice(Seat $seat, VehicleSchedule $schedule): float
    {
        // Priority: schedule > route > vehicle > seat base price
        $price = \App\Models\SeatLayout\SeatPrice::where(function ($query) use ($schedule, $seat) {
            $query->where('schedule_id', $schedule->id)
                ->orWhere(function ($q) use ($schedule, $seat) {
                    $q->where('route_id', $schedule->route_id)
                      ->whereNull('schedule_id');
                })
                ->orWhere(function ($q) use ($schedule, $seat) {
                    $q->where('vehicle_id', $schedule->vehicle_id)
                      ->whereNull('route_id')
                      ->whereNull('schedule_id');
                })
                ->orWhere(function ($q) use ($seat) {
                    $q->where('seat_id', $seat->id)
                      ->whereNull('vehicle_id')
                      ->whereNull('route_id')
                      ->whereNull('schedule_id');
                })
                ->orWhere(function ($q) use ($seat) {
                    $q->where('seat_type', $seat->seat_type)
                      ->whereNull('seat_id')
                      ->whereNull('vehicle_id')
                      ->whereNull('route_id')
                      ->whereNull('schedule_id');
                });
        })
        ->where('is_active', true)
        ->where(function ($query) {
            $query->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
        })
        ->where(function ($query) {
            $query->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
        })
        ->orderByRaw('CASE 
            WHEN schedule_id IS NOT NULL THEN 1
            WHEN route_id IS NOT NULL THEN 2
            WHEN vehicle_id IS NOT NULL THEN 3
            WHEN seat_id IS NOT NULL THEN 4
            ELSE 5
        END')
        ->first();

        return $price ? $price->adult_price : ($seat->base_price ?? 0);
    }

    /**
     * Organize seats by rows for display.
     */
    protected function organizeByRows(array $seats): array
    {
        $rows = [];
        foreach ($seats as $seat) {
            $row = $seat['row'];
            if (!isset($rows[$row])) {
                $rows[$row] = [];
            }
            $rows[$row][] = $seat;
        }
        
        // Sort by column within each row
        foreach ($rows as &$rowSeats) {
            usort($rowSeats, fn($a, $b) => $a['column'] <=> $b['column']);
        }
        
        ksort($rows);
        return array_values($rows);
    }
}
