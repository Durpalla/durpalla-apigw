<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\Merchant\MerchantBookingsController;
use App\Http\Controllers\Api\v1\Merchant\MerchantCapabilityController;
use App\Http\Controllers\Api\v1\Merchant\MerchantFareController;
use App\Http\Controllers\Api\v1\Merchant\MerchantGatewayController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelAvailabilityController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelBookingController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelChildPolicyController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelFacilityController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelHoldController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelImageController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelInventoryController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelMetaController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelReportController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelRoomController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelRoomImageController;
use App\Http\Controllers\Api\v1\Merchant\MerchantHotelRoomUnitController;
use App\Http\Controllers\Api\v1\Merchant\MerchantNotificationController;
use App\Http\Controllers\Api\v1\Merchant\MerchantProfileController;
use App\Http\Controllers\Api\v1\Merchant\MerchantPropertyController;
use App\Http\Controllers\Api\v1\Merchant\MerchantReportController;
use App\Http\Controllers\Api\v1\Merchant\MerchantRouteController;
use App\Http\Controllers\Api\v1\Merchant\MerchantSettlementRequestController;
use App\Http\Controllers\Api\v1\Merchant\MerchantStaffController;
use App\Http\Controllers\Api\v1\Merchant\MerchantSupportTicketController;
use App\Http\Controllers\Api\v1\Merchant\MerchantTripBookingController;
use App\Http\Controllers\Api\v1\Merchant\MerchantTripController;
use App\Http\Controllers\Api\v1\Merchant\MerchantTripLayoutController;
use App\Http\Controllers\Api\v1\Merchant\MerchantTripSeatLockController;
use App\Http\Controllers\Api\v1\Merchant\MerchantVehicleImageController;
use App\Http\Controllers\Api\v1\Merchant\MerchantWalletStatementController;

Route::middleware(['auth:merchant_api,merchant_staff_api', 'merchant.active'])->group(function () {

        Route::get('capabilities', [MerchantCapabilityController::class, 'show']);
        Route::get('profile', [MerchantProfileController::class, 'show']);
        Route::patch('profile', [MerchantProfileController::class, 'update']);
        Route::post('profile/logo', [MerchantProfileController::class, 'uploadLogo']);
        Route::post('profile/avatar', [MerchantProfileController::class, 'uploadAvatar']);
        Route::post('profile/password', [MerchantProfileController::class, 'changePassword']);
        Route::post('profile/2fa/email', [MerchantProfileController::class, 'enableEmail2fa']);
        Route::post('profile/2fa/disable', [MerchantProfileController::class, 'disable2fa']);
        Route::post('profile/2fa/authenticator/setup', [MerchantProfileController::class, 'authenticatorSetup']);
        Route::post('profile/2fa/authenticator/confirm', [MerchantProfileController::class, 'authenticatorConfirm']);

        Route::get('notifications', [MerchantNotificationController::class, 'index']);
        Route::get('notifications/unread-count', [MerchantNotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [MerchantNotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [MerchantNotificationController::class, 'markRead']);

        // Support tickets (merchant panel)
        Route::get('support/tickets/counts', [MerchantSupportTicketController::class, 'counts']);
        Route::get('support/tickets', [MerchantSupportTicketController::class, 'index']);
        Route::post('support/tickets', [MerchantSupportTicketController::class, 'store']);
        Route::get('support/tickets/{ticket}', [MerchantSupportTicketController::class, 'show']);
        Route::post('support/tickets/{ticket}/replies', [MerchantSupportTicketController::class, 'reply']);

        Route::get('properties', [MerchantPropertyController::class, 'index']);
        Route::post('properties', [MerchantPropertyController::class, 'store']);
        Route::get('properties/{id}/layout', [MerchantPropertyController::class, 'layoutSummary'])->whereNumber('id');
        Route::get('properties/{id}/layout/floors', [MerchantPropertyController::class, 'layoutFloors'])->whereNumber('id');
        Route::get('properties/{id}/layout/{type}', [MerchantPropertyController::class, 'layoutMap'])
            ->whereNumber('id')
            ->where('type', 'seat|cabin|sofa');
        Route::post('properties/{id}/layout/import', [MerchantPropertyController::class, 'importLayout'])->whereNumber('id');
        Route::get('properties/{id}/images', [MerchantVehicleImageController::class, 'index'])->whereNumber('id');
        Route::post('properties/{id}/images', [MerchantVehicleImageController::class, 'store'])->whereNumber('id');
        Route::delete('properties/{id}/images/{imageId}', [MerchantVehicleImageController::class, 'destroy'])
            ->whereNumber('id')->whereNumber('imageId');
        Route::post('properties/{id}/photo', [MerchantVehicleImageController::class, 'uploadPhoto'])->whereNumber('id');
        Route::get('properties/{id}', [MerchantPropertyController::class, 'show'])->whereNumber('id');
        Route::put('properties/{id}', [MerchantPropertyController::class, 'update'])->whereNumber('id');
        Route::patch('properties/{id}/status', [MerchantPropertyController::class, 'updateStatus'])->whereNumber('id');
        Route::get('routes/suggest', [MerchantPropertyController::class, 'routeSuggest']);
        Route::get('bookings/lookup', [MerchantBookingsController::class, 'lookup']);
        Route::get('bookings', [MerchantBookingsController::class, 'index']);
        Route::get('wallet/statements', [MerchantWalletStatementController::class, 'index']);
        // Accept legacy BK-{id} and public PNR (D{ymd}-L####-L#### / legacy 5-digit segments).
        $merchantBookingSlug = '[Bb][Kk]-[0-9]+|[Dd][0-9]{6}-[A-Za-z][0-9]{4}-[A-Za-z][0-9]{4}|[Dd][0-9]{6}-[A-Za-z][0-9]{5}-[A-Za-z][0-9]{5}';
        Route::get('bookings/{bookingSlug}', [MerchantBookingsController::class, 'show'])
            ->where('bookingSlug', $merchantBookingSlug);
        Route::post('bookings/{bookingSlug}/collect', [MerchantBookingsController::class, 'collect'])
            ->where('bookingSlug', $merchantBookingSlug);
        Route::post('bookings/{bookingSlug}/payment-link', [MerchantBookingsController::class, 'paymentLink'])
            ->where('bookingSlug', $merchantBookingSlug);
        Route::post('bookings/{bookingSlug}/payment-qr', [MerchantBookingsController::class, 'paymentQr'])
            ->where('bookingSlug', $merchantBookingSlug);
        Route::post('bookings/{bookingSlug}/attach-payment', [MerchantBookingsController::class, 'attachPayment'])
            ->where('bookingSlug', $merchantBookingSlug);
        Route::post('bookings/{bookingSlug}/cancel', [MerchantBookingsController::class, 'cancel'])
            ->where('bookingSlug', $merchantBookingSlug);

        Route::get('gateways', [MerchantGatewayController::class, 'index']);
        Route::get('gateways/templates', [MerchantGatewayController::class, 'templates']);
        Route::post('gateways/enable', [MerchantGatewayController::class, 'enable']);
        Route::put('gateways/{id}', [MerchantGatewayController::class, 'update'])->whereNumber('id');
        Route::delete('gateways/{id}', [MerchantGatewayController::class, 'destroy'])->whereNumber('id');
        Route::put('gateways/{id}/credentials', [MerchantGatewayController::class, 'updateCredentials'])->whereNumber('id');
        Route::put('gateways/{id}/params', [MerchantGatewayController::class, 'updateParams'])->whereNumber('id');

        Route::get('trips', [MerchantTripController::class, 'index']);
        Route::get('trips/{id}/stats', [MerchantTripController::class, 'stats'])->whereNumber('id');
        Route::get('trips/{id}', [MerchantTripController::class, 'show'])->whereNumber('id');
        Route::post('trips', [MerchantTripController::class, 'store']);
        Route::put('trips/{id}', [MerchantTripController::class, 'update'])->whereNumber('id');
        Route::get('trips/{tripId}/floors', [MerchantTripLayoutController::class, 'floors'])->whereNumber('tripId');
        Route::get('trips/{tripId}/layout/{type}', [MerchantTripLayoutController::class, 'layout'])->whereNumber('tripId');
        Route::post('trips/{tripId}/reserve', [MerchantTripLayoutController::class, 'reserve'])->whereNumber('tripId');
        Route::post('trips/{tripId}/seats/lock', [MerchantTripSeatLockController::class, 'lock']);
        Route::post('trips/{tripId}/seats/release', [MerchantTripSeatLockController::class, 'release']);
        Route::post('trips/{tripId}/bookings', [MerchantTripBookingController::class, 'store'])->whereNumber('tripId')->middleware('saas.subscription');

        Route::get('settlement-requests', [MerchantSettlementRequestController::class, 'index']);
        Route::patch('settlement-requests/{id}/approve', [MerchantSettlementRequestController::class, 'approve'])->whereNumber('id');
        Route::patch('settlement-requests/{id}/decline', [MerchantSettlementRequestController::class, 'decline'])->whereNumber('id');

        Route::get('routes/service-types', [MerchantRouteController::class, 'serviceTypes']);
        Route::get('routes/name-preview', [MerchantRouteController::class, 'namePreview']);
        Route::get('routes', [MerchantRouteController::class, 'index']);
        Route::post('routes', [MerchantRouteController::class, 'store']);
        Route::get('routes/{id}', [MerchantRouteController::class, 'show'])->whereNumber('id');
        Route::get('routes/{routeId}/fares', [MerchantFareController::class, 'index'])->whereNumber('routeId');
        Route::post('routes/{routeId}/fares', [MerchantFareController::class, 'store'])->whereNumber('routeId');
        Route::post('routes/{routeId}/fares/bulk', [MerchantFareController::class, 'bulk'])->whereNumber('routeId');
        Route::put('fares/{id}', [MerchantFareController::class, 'update'])->whereNumber('id');
        Route::delete('fares/{id}', [MerchantFareController::class, 'destroy'])->whereNumber('id');
        Route::get('ghats/suggest', [MerchantRouteController::class, 'ghatSuggest']);

        Route::get('staff/roles', [MerchantStaffController::class, 'rolesForCreate']);
        Route::get('staff/permissions', [MerchantStaffController::class, 'permissionOptions']);
        Route::post('staff', [MerchantStaffController::class, 'store']);
        Route::get('staff', [MerchantStaffController::class, 'index']);
        Route::get('staff/{id}', [MerchantStaffController::class, 'show'])->whereNumber('id');
        Route::patch('staff/{id}', [MerchantStaffController::class, 'update'])->whereNumber('id');
        Route::patch('staff/{id}/status', [MerchantStaffController::class, 'updateStatus'])->whereNumber('id');
        Route::put('staff/{id}/vehicles', [MerchantStaffController::class, 'assignVehicles'])->whereNumber('id');
        Route::get('staff/{id}/summary', [MerchantStaffController::class, 'summary'])->whereNumber('id');
        Route::get('staff/{id}/activities', [MerchantStaffController::class, 'activities'])->whereNumber('id');

        Route::get('reports/summary', [MerchantReportController::class, 'summary']);
        Route::get('reports/export', [MerchantReportController::class, 'export']);
        Route::get('dashboard/stats', [MerchantReportController::class, 'dashboardStats']);

        // Hotel Management (merchant-scoped)
        Route::get('hotels/meta/facilities', [MerchantHotelMetaController::class, 'facilities']);
        Route::get('hotels/meta/room-types', [MerchantHotelMetaController::class, 'roomTypes']);
        Route::get('hotels/meta/rate-plans', [MerchantHotelMetaController::class, 'ratePlans']);

        Route::get('hotels', [MerchantHotelController::class, 'index']);
        Route::post('hotels', [MerchantHotelController::class, 'store']);
        Route::get('hotels/{id}', [MerchantHotelController::class, 'show'])->whereNumber('id');
        Route::put('hotels/{id}', [MerchantHotelController::class, 'update'])->whereNumber('id');
        Route::patch('hotels/{id}/status', [MerchantHotelController::class, 'updateStatus'])->whereNumber('id');

        Route::post('hotels/{hotelId}/facilities-sync', [MerchantHotelFacilityController::class, 'sync'])->whereNumber('hotelId');

        Route::get('hotels/{hotelId}/images', [MerchantHotelImageController::class, 'index'])->whereNumber('hotelId');
        Route::post('hotels/{hotelId}/images', [MerchantHotelImageController::class, 'store'])->whereNumber('hotelId');
        Route::delete('hotels/{hotelId}/images/{imageId}', [MerchantHotelImageController::class, 'destroy'])
            ->whereNumber('hotelId')->whereNumber('imageId');
        Route::post('hotels/{hotelId}/images/reorder', [MerchantHotelImageController::class, 'reorder'])->whereNumber('hotelId');

        Route::get('hotels/{hotelId}/rooms', [MerchantHotelRoomController::class, 'index'])->whereNumber('hotelId');
        Route::post('hotels/{hotelId}/rooms', [MerchantHotelRoomController::class, 'store'])->whereNumber('hotelId');
        Route::patch('hotels/{hotelId}/rooms/{roomId}', [MerchantHotelRoomController::class, 'update'])
            ->whereNumber('hotelId')->whereNumber('roomId');

        Route::get('hotels/{hotelId}/rooms/{roomId}/units', [MerchantHotelRoomUnitController::class, 'index'])
            ->whereNumber('hotelId')->whereNumber('roomId');
        Route::post('hotels/{hotelId}/rooms/{roomId}/units', [MerchantHotelRoomUnitController::class, 'store'])
            ->whereNumber('hotelId')->whereNumber('roomId');

        Route::get('hotels/{hotelId}/rooms/{roomId}/images', [MerchantHotelRoomImageController::class, 'index'])
            ->whereNumber('hotelId')->whereNumber('roomId');
        Route::post('hotels/{hotelId}/rooms/{roomId}/images', [MerchantHotelRoomImageController::class, 'store'])
            ->whereNumber('hotelId')->whereNumber('roomId');
        Route::delete('hotels/{hotelId}/rooms/{roomId}/images/{imageId}', [MerchantHotelRoomImageController::class, 'destroy'])
            ->whereNumber('hotelId')->whereNumber('roomId')->whereNumber('imageId');

        Route::put('hotels/{hotelId}/inventory', [MerchantHotelInventoryController::class, 'upsert'])->whereNumber('hotelId');
        Route::get('hotels/{hotelId}/availability', [MerchantHotelAvailabilityController::class, 'index'])
            ->whereNumber('hotelId');

        Route::get('hotels/{hotelId}/child-policies', [MerchantHotelChildPolicyController::class, 'index'])->whereNumber('hotelId');
        Route::post('hotels/{hotelId}/child-policies', [MerchantHotelChildPolicyController::class, 'store'])->whereNumber('hotelId');
        Route::patch('hotels/{hotelId}/child-policies/{policyId}', [MerchantHotelChildPolicyController::class, 'update'])
            ->whereNumber('hotelId')->whereNumber('policyId');
        Route::delete('hotels/{hotelId}/child-policies/{policyId}', [MerchantHotelChildPolicyController::class, 'destroy'])
            ->whereNumber('hotelId')->whereNumber('policyId');

        Route::post('hotel-holds', [MerchantHotelHoldController::class, 'store'])->middleware('saas.subscription');
        Route::get('hotel-holds', [MerchantHotelHoldController::class, 'index']);
        Route::delete('hotel-holds/{id}', [MerchantHotelHoldController::class, 'destroy'])->whereNumber('id');
        Route::post('hotel-holds/{id}/confirm', [MerchantHotelHoldController::class, 'confirm'])
            ->whereNumber('id')
            ->middleware('saas.subscription');

        Route::post('hotel-bookings', [MerchantHotelBookingController::class, 'store'])->middleware('saas.subscription');
        Route::get('hotel-bookings', [MerchantHotelBookingController::class, 'index']);
        Route::get('hotel-bookings/{id}', [MerchantHotelBookingController::class, 'show'])->whereNumber('id');
        Route::post('hotel-bookings/{id}/cancel', [MerchantHotelBookingController::class, 'cancel'])->whereNumber('id');
        Route::post('hotel-bookings/{id}/check-in', [MerchantHotelBookingController::class, 'checkIn'])->whereNumber('id');
        Route::post('hotel-bookings/{id}/check-out', [MerchantHotelBookingController::class, 'checkOut'])->whereNumber('id');

        Route::get('hotels/reports/summary', [MerchantHotelReportController::class, 'summary']);
        Route::get('hotels/reports/export', [MerchantHotelReportController::class, 'export']);

});
