<?php

namespace App\Services\SeatLayout;

use App\Models\SeatLayout\Seat;
use App\Models\SeatLayout\TripSeatInventory;
use App\Models\VehicleSchedule;
use Illuminate\Support\Facades\DB;

class SeatRuleEngine
{
    /**
     * Validate seat selection against gender and adjacency rules.
     */
    public function validateSeatSelection(
        VehicleSchedule $schedule,
        array $seatIds,
        ?string $passengerGender = null
    ): array {
        $errors = [];
        $warnings = [];

        foreach ($seatIds as $seatId) {
            $seat = Seat::find($seatId);
            if (!$seat) {
                $errors[] = "Seat ID {$seatId} not found";
                continue;
            }

            $inventory = TripSeatInventory::where('schedule_id', $schedule->id)
                ->where('seat_id', $seatId)
                ->first();

            if (!$inventory || !$inventory->isAvailable()) {
                $errors[] = "Seat {$seat->seat_number} is not available";
                continue;
            }

            // Check gender rules
            $genderValidation = $this->validateGenderRule($schedule, $seat, $passengerGender);
            if (!$genderValidation['valid']) {
                $errors = array_merge($errors, $genderValidation['errors']);
            }
            if (!empty($genderValidation['warnings'])) {
                $warnings = array_merge($warnings, $genderValidation['warnings']);
            }

            // Check adjacency rules
            $adjacencyValidation = $this->validateAdjacencyRules($schedule, $seat, $seatIds, $passengerGender);
            if (!$adjacencyValidation['valid']) {
                $errors = array_merge($errors, $adjacencyValidation['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate gender rule for a seat.
     */
    protected function validateGenderRule(
        VehicleSchedule $schedule,
        Seat $seat,
        ?string $passengerGender
    ): array {
        $errors = [];
        $warnings = [];

        if (!$passengerGender) {
            return ['valid' => true, 'errors' => [], 'warnings' => []];
        }

        switch ($seat->gender_rule) {
            case 'male_only':
                if ($passengerGender !== 'male') {
                    $errors[] = "Seat {$seat->seat_number} is reserved for male passengers only";
                }
                break;

            case 'female_only':
                if ($passengerGender !== 'female') {
                    $errors[] = "Seat {$seat->seat_number} is reserved for female passengers only";
                }
                break;

            case 'adjacent_same':
                // Check adjacent seats
                $adjacentSeats = $this->getAdjacentSeats($schedule, $seat);
                foreach ($adjacentSeats as $adjacentSeat) {
                    $adjacentInventory = TripSeatInventory::where('schedule_id', $schedule->id)
                        ->where('seat_id', $adjacentSeat->id)
                        ->first();

                    if ($adjacentInventory && 
                        $adjacentInventory->passenger_gender && 
                        $adjacentInventory->passenger_gender !== $passengerGender) {
                        $warnings[] = "Seat {$seat->seat_number} is adjacent to a {$adjacentInventory->passenger_gender} passenger. Consider selecting a different seat.";
                    }
                }
                break;

            case 'any':
            default:
                // No restrictions
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate adjacency rules.
     */
    protected function validateAdjacencyRules(
        VehicleSchedule $schedule,
        Seat $seat,
        array $selectedSeatIds,
        ?string $passengerGender
    ): array {
        $errors = [];

        if (!$seat->adjacent_seats || empty($seat->adjacent_seats)) {
            return ['valid' => true, 'errors' => []];
        }

        // Check if any adjacent seats are already booked with different gender
        $adjacentSeats = Seat::whereIn('id', $seat->adjacent_seats)->get();
        
        foreach ($adjacentSeats as $adjacentSeat) {
            // Skip if this adjacent seat is also selected
            if (in_array($adjacentSeat->id, $selectedSeatIds)) {
                continue;
            }

            $adjacentInventory = TripSeatInventory::where('schedule_id', $schedule->id)
                ->where('seat_id', $adjacentSeat->id)
                ->first();

            if ($adjacentInventory && 
                $adjacentInventory->passenger_gender && 
                $passengerGender &&
                $adjacentInventory->passenger_gender !== $passengerGender &&
                $adjacentSeat->gender_rule === 'adjacent_same') {
                $errors[] = "Cannot book seat {$seat->seat_number} adjacent to seat {$adjacentSeat->seat_number} due to gender restrictions";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get adjacent seats for a given seat.
     */
    protected function getAdjacentSeats(VehicleSchedule $schedule, Seat $seat): array
    {
        if (!$seat->adjacent_seats) {
            return [];
        }

        return Seat::whereIn('id', $seat->adjacent_seats)->get()->all();
    }

    /**
     * Lock seats temporarily during booking process.
     */
    public function lockSeats(VehicleSchedule $schedule, array $seatIds, string $lockKey, int $lockDuration = 300): bool
    {
        return DB::transaction(function () use ($schedule, $seatIds, $lockKey, $lockDuration) {
            $lockedUntil = now()->addSeconds($lockDuration);

            foreach ($seatIds as $seatId) {
                $inventory = TripSeatInventory::where('schedule_id', $schedule->id)
                    ->where('seat_id', $seatId)
                    ->lockForUpdate()
                    ->first();

                if (!$inventory || !$inventory->isAvailable()) {
                    // Rollback if any seat is not available
                    return false;
                }

                $inventory->update([
                    'status' => 'locked',
                    'locked_by' => $lockKey,
                    'locked_until' => $lockedUntil,
                ]);
            }

            return true;
        });
    }

    /**
     * Release locked seats.
     */
    public function releaseSeats(VehicleSchedule $schedule, array $seatIds, string $lockKey): void
    {
        TripSeatInventory::where('schedule_id', $schedule->id)
            ->whereIn('seat_id', $seatIds)
            ->where('locked_by', $lockKey)
            ->where('status', 'locked')
            ->update([
                'status' => 'available',
                'locked_by' => null,
                'locked_until' => null,
            ]);
    }

    /**
     * Book seats (mark as booked).
     */
    public function bookSeats(
        VehicleSchedule $schedule,
        array $seatIds,
        int $bookingItemId,
        ?string $passengerGender = null
    ): void {
        TripSeatInventory::where('schedule_id', $schedule->id)
            ->whereIn('seat_id', $seatIds)
            ->update([
                'status' => 'booked',
                'booking_item_id' => $bookingItemId,
                'passenger_gender' => $passengerGender,
                'locked_by' => null,
                'locked_until' => null,
            ]);
    }

    /**
     * Release booked seats (cancellation).
     */
    public function releaseBookedSeats(VehicleSchedule $schedule, array $seatIds): void
    {
        TripSeatInventory::where('schedule_id', $schedule->id)
            ->whereIn('seat_id', $seatIds)
            ->where('status', 'booked')
            ->update([
                'status' => 'available',
                'booking_item_id' => null,
                'passenger_gender' => null,
            ]);
    }
}
