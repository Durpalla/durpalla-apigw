<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\WithdrawalActionEvent;
use App\Listeners\PruneOldTokens;
use App\Listeners\RevokeOldTokens;
use App\Listeners\SendNewUserNotification;
use App\Listeners\WithdrawalActionListener;
use App\Models\Agent;
use App\Models\AgentWithdrawal;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingCancellation;
use App\Models\CabinLock;
use App\Models\Coupon;
use App\Models\Gateway;
use App\Models\Ghat;
use App\Models\Merchant;
use App\Models\Party;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Models\VehicleSupervisor;
use App\Observers\AgentObserver;
use App\Observers\AgentWithdrawalObserver;
use App\Observers\BookingCancellationObserver;
use App\Observers\BookingItemObserver;
use App\Observers\BookingObserver;
use App\Observers\CabinLockObserver;
use App\Observers\CouponObserver;
use App\Observers\GatewayObserver;
use App\Observers\GhatObserver;
use App\Observers\MerchantObserver;
use App\Observers\PartyObserver;
use App\Observers\ScheduleObserver;
use App\Observers\ServiceObserver;
use App\Observers\SupervisorObserver;
use App\Observers\UserObserver;
use App\Observers\VehicleObserver;
use App\Observers\VehicleRouteObserver;
use App\Events\BookingCompleteEvent as AppBookingCompleteEvent;
use App\Listeners\RecordFinancialEventsOnBookingPaid;
use App\Models\User;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\RefreshTokenCreated;
use Modules\Booking\Events\BookingCancelledEvent;
use Modules\Booking\Events\BookingCompleteEvent;
use Modules\Booking\Events\BookingFailedEvent;
use Modules\Booking\Events\BookingPendingHandleEvent;
use Modules\Booking\Listeners\BookingCancelledEventListener;
use Modules\Booking\Listeners\BookingCompleteEventListener;
use Modules\Booking\Listeners\BookingFailedEventListener;
use Modules\Booking\Listeners\BookingPendingHandleEventListener;
use Modules\Cancellation\Events\CancellationRequestEvent;
use Modules\Cancellation\Listeners\CancellationRequestEventListener;
use Modules\Payment\Events\PaymentCompleteEvent;
use Modules\Payment\Listeners\PaymentSuccessListener;
use Modules\Vehicle\Events\VehicleInactiveEvent;
use Modules\Vehicle\Listeners\VehicleInactiveEventListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            SendNewUserNotification::class,
        ],
        AppBookingCompleteEvent::class => [
            RecordFinancialEventsOnBookingPaid::class,
        ],
        'Laravel\Passport\Events\AccessTokenCreated' => [
            'App\Listeners\RevokeOldTokens',
        ],

        'Laravel\Passport\Events\RefreshTokenCreated' => [
            'App\Listeners\PruneOldTokens',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Booking::observe(BookingObserver::class);
        BookingItem::observe(BookingItemObserver::class);
        BookingCancellation::observe(BookingCancellationObserver::class);
        CabinLock::observe(CabinLockObserver::class);
    }
}
