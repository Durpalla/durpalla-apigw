<?php

use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\VehicleSchedule;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{toUserId}', function ($user, $toUserId) {
    return (int) $user->id === (int) $toUserId;
});

Broadcast::channel('New.Notification', function ($user, $post) {
    return (int) $user->id === (int) ($post->user_id ?? 0);
});

Broadcast::channel('notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Merchant Desk + supervisor layout updates.
 * Wire name: presence-trip.{tripId} (Laravel strips the presence- prefix here).
 */
Broadcast::channel('trip.{tripId}', function ($user, string $tripId) {
    $numeric = (int) preg_replace('/^TRIP-/i', '', $tripId);
    $trip = VehicleSchedule::query()->find($numeric);
    if (! $trip) {
        return false;
    }

    $merchantOwnerId = null;
    if ($user instanceof Merchant) {
        $merchantOwnerId = (int) $user->id;
    } elseif ($user instanceof MerchantStaff && $user->merchant_id) {
        $merchantOwnerId = (int) $user->merchant_id;
    } elseif (! empty($user->merchant_id) && (int) $user->merchant_id > 0) {
        $merchantOwnerId = (int) $user->merchant_id;
    }

    if ($merchantOwnerId !== null
        && $trip->merchant_id !== null
        && (int) $trip->merchant_id === $merchantOwnerId) {
        return [
            'id' => (string) $user->id,
            'name' => $user->name ?? 'Merchant',
        ];
    }

    $vehicles = method_exists($user, 'vehicles') ? $user->vehicles : null;
    if ($vehicles) {
        $vehicleIds = $vehicles->pluck('vehicle_id')->filter()->unique()->values()->all();
        if ($vehicleIds === []) {
            $vehicleIds = $vehicles->pluck('id')->filter()->unique()->values()->all();
        }
        if (in_array((int) $trip->vehicle_id, array_map('intval', $vehicleIds), true)) {
            return [
                'id' => (string) $user->id,
                'name' => $user->name ?? 'Supervisor',
            ];
        }
    }

    return false;
});
