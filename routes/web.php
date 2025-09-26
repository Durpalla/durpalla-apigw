<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Dashboard\AgentController;
use App\Http\Controllers\Dashboard\BannerController;
use App\Http\Controllers\Dashboard\BlogCatagoryController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\BookingCancellationController;
use App\Http\Controllers\Dashboard\BookingController;
use App\Http\Controllers\Dashboard\CabinTypeController;
use App\Http\Controllers\Dashboard\CouponController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DeckFareController;
use App\Http\Controllers\Dashboard\DesignationController;
use App\Http\Controllers\Dashboard\DiscountController;
use App\Http\Controllers\Dashboard\FirebaseController;
use App\Http\Controllers\Dashboard\GhatController;
use App\Http\Controllers\Dashboard\MerchantController;
use App\Http\Controllers\Dashboard\MerchantReportController;
use App\Http\Controllers\Dashboard\OptionController;
use App\Http\Controllers\Dashboard\OtherController;
use App\Http\Controllers\Dashboard\PageController;
//use App\Http\Controllers\Dashboard\PartnerController;
use App\Http\Controllers\Dashboard\PartyController;
use App\Http\Controllers\Dashboard\PaymentController;
use App\Http\Controllers\Dashboard\PermissionController;
use App\Http\Controllers\Dashboard\ScheduleCabinMappingsController;
use App\Http\Controllers\Dashboard\ServiceController;
use App\Http\Controllers\Dashboard\ShadowSessionController;
use App\Http\Controllers\Dashboard\SocialPosterController;
use App\Http\Controllers\Dashboard\SponsorController;
use App\Http\Controllers\Dashboard\SupervisorController;
use App\Http\Controllers\Dashboard\VehicleCabinController;
use App\Http\Controllers\Dashboard\VehicleController;
use App\Http\Controllers\Dashboard\VehicleRouteController;
use App\Http\Controllers\Dashboard\VehicleScheduleController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\SslCommerzPaymentController;
use App\Http\Controllers\TicketPrintController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\RoleController;
use Modules\Auth\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::redirect('/', '/login', 301);
Route::middleware(['throttle:100,1'])->group(function () {
    Route::prefix('checkout')->group(function () {
        Route::get('/', [CheckoutController::class, "index"])->name('checkout.index');
        Route::get('/paynow/{id}', [CheckoutController::class, "paynow"])->name('checkout.paynow');
        Route::post('/token', [CheckoutController::class, "token"])->name('checkout.token');
        Route::post('/create', [CheckoutController::class, "create"])->name('checkout.create');
        Route::post('/execute', [CheckoutController::class, "execute"])->name('checkout.execute');
        Route::post('/intend', [CheckoutController::class, "intend"])->name('checkout.intend');
        Route::get('/success/{string}', [CheckoutController::class, "success"])->name('checkout.success');
        Route::get('/fail', [CheckoutController::class, "fail"])->name('checkout.fail');
        Route::get('/bkash/complete', [CheckoutController::class, "bkashComplete"])->name('checkout.bkash.complete');
        Route::get('/nagad/execute/{bookingId}', [CheckoutController::class, "nagadCheckout"])->name('nagad.checkout');
        Route::get('/nagad/callback', [CheckoutController::class, "nagadCallback"])->name('nagad.callback');
    });

    //these route needs for sslcommerz payment notifications
    Route::prefix('sslcommerz')->group(function () {
        Route::post('/success', [SslCommerzPaymentController::class, "paymentSuccess"])->name('sslcommerz.success');
        Route::post('/fail', [SslCommerzPaymentController::class, "fail"])->name('sslcommerz.fail');
        Route::post('/cancel', [SslCommerzPaymentController::class, "cancel"])->name('sslcommerz.cancel');
        Route::post('/ipn', [SslCommerzPaymentController::class, "ipn"])->name('sslcommerz.ipn');
    });

    Route::get('social/share/{id}', [SocialPosterController::class, "show"])->name('social.share');

    Auth::routes(['register' => false]);
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, "login"])->name('auth.login');
        Route::get('/check', [AuthController::class, "check"])->name('auth.check');
        Route::post('/verify', [AuthController::class, "verify"])->name('auth.verify');
    });

    Route::post('/ipn', [SslCommerzPaymentController::class, "ipn"]);
    //SSLCOMMERZ END
    Route::get('/download/{id}', [FrontController::class, "downloadInvoice"])->name('invoice.download');
    Route::get('/ticket/print/{id}', [TicketPrintController::class, "show"])->name('ticket.print');

    Route::middleware(['auth', 'role:admin|officer|support|merchant|manager|supervisor|counter-officer'])->prefix('admin')->group(function () {
        Route::resources([
            'merchant_reports' => MerchantReportController::class,
            'shadow_sessions' => ShadowSessionController::class,
            'parties' => PartyController::class,
            'services' => ServiceController::class,
            'firebase' => FirebaseController::class
        ]);
        Route::get('/', [DashboardController::class, "index"])->name('home');
        Route::post('dashboard-search', [DashboardController::class, "search"])->name('dashboard.search');
        Route::get('/quickbook', [OtherController::class, "quickbook"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.quickbook');
        Route::get('/trip/{id}', [OtherController::class, "getTrip"])
            ->name('dashboard.quickbook.trip');
        Route::get('/other/quickbook', [OtherController::class, "otherQuickBook"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.other.quickbook');
        Route::get('/other/confirm-booking', [OtherController::class, "confirmation"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.other.confirmation');
        Route::post('/quickbook/add', [OtherController::class, "addToCart"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.quickbook.add');
        Route::post('/quickbook/add-deck-cart', [OtherController::class, "addDeckCart"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.quickbook.addDeckCart');
        Route::post('/quickbook/remove-cart', [OtherController::class, "removeCartItem"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.quickbook.removeCartItem');
        Route::post('/quickbook/confirm', [OtherController::class, "bookingConfirm"])
            ->middleware('role_or_permission:admin|other-quick-book|booking-quick')
            ->name('dashboard.quickbook.confirm');

        //Agent routes
        Route::prefix('/agent')->group(function () {
            Route::get('/{id}/bookings', [AgentController::class, "bookings"])->name('agent.bookings');
            Route::get('/suggest', [AgentController::class, "suggest"])->name('agent.suggest');
//            Route::resource('commission', 'Dashboard/AgentCommissionController');
//            Route::resource('withdrawal', 'Dashboard/AgentWithdrawalController');
        });
//        Route::resource('agent', 'AgentController');

        //Partner routes
//        Route::prefix('/partner')->group(function () {
//            Route::get('/vehicles', [PartnerController::class, "vehicles"])->name('partner.vehicles');
//            Route::get('/{id}/bookings', [PartnerController::class, "bookings"])->name('partner.bookings');
//            Route::get('/suggest', [PartnerController::class, "suggest"])->name('partner.suggest');
//            Route::get('/suggest-vehicles', [PartnerController::class, "suggestVehicles"])->name('partner.suggest.vehicles');
////            Route::resource('partner_vehicle', 'PartnerVehicleController');
//        });
//        Route::resource('partner', 'PartnerController');

        Route::prefix('customer')->group(function () {
            Route::get('/', [CustomerController::class, "index"])
                ->middleware('role_or_permission:admin|customer-list|traveller-manage')
                ->name('dashboard.customer.index');
            Route::get('/create', [CustomerController::class, "create"])
                ->middleware('role_or_permission:admin|customer-create|traveller-add')
                ->name('dashboard.customer.create');
            Route::post('/store', [CustomerController::class, "store"])
                ->middleware('role_or_permission:admin|customer-create|traveller-add')
                ->name('dashboard.customer.store');
            Route::get('/show/{id}', [CustomerController::class, "show"])
                ->middleware('role_or_permission:admin|customer-show|traveller-show')
                ->name('dashboard.customer.show');
            Route::get('/edit/{id}', [CustomerController::class, "edit"])
                ->middleware('role_or_permission:admin|customer-edit|traveller-update')
                ->name('dashboard.customer.edit');
            Route::put('/update/{id}', [CustomerController::class, "update"])
                ->middleware('role_or_permission:admin|customer-edit|traveller-edit')
                ->name('dashboard.customer.update');
            Route::delete('/delete/{id}', [CustomerController::class, "destroy"])
                ->middleware('role_or_permission:admin|customer-delete|traveller-delete')
                ->name('dashboard.customer.delete');
            Route::get('/suggest', [CustomerController::class, "suggest"])->name('dashboard.customer.suggest');
            Route::post('/action', [CustomerController::class, "action"])
                ->middleware('role_or_permission:admin|customer-action|traveller-action')
                ->name('dashboard.customer.action');
            Route::get('/bookings/{id}', [CustomerController::class, "bookings"])->name('dashboard.customer.booking');
        });

        Route::prefix('supervisor')->group(function () {
            Route::post('/extend-nid-visibility', [SupervisorController::class, "extendNidVisibility"])->name('supervisor.nidvisibility');
        });

        Route::prefix('merchant')->group(function () {
            Route::get('/', [MerchantController::class, "index"])
                ->middleware('role_or_permission:admin|merchant-list')
                ->name('dashboard.merchant.index');
            Route::get('/create', [MerchantController::class, "create"])
                ->middleware('role_or_permission:admin|merchant-create')
                ->name('dashboard.merchant.create');
            Route::post('/store', [MerchantController::class, "store"])
                ->middleware('role_or_permission:admin|merchant-create')
                ->name('dashboard.merchant.store');
            Route::get('/edit/{id}', [MerchantController::class, "edit"])
                ->middleware('role_or_permission:admin|merchant-edit')
                ->name('dashboard.merchant.edit');
            Route::put('/update/{id}', [MerchantController::class, "update"])
                ->middleware('role_or_permission:admin|merchant-edit')
                ->name('dashboard.merchant.update');
            Route::get('/show/{id}', [MerchantController::class, "show"])
                ->middleware('role_or_permission:admin|merchant-show|merchant-view')
                ->name('dashboard.merchant.show');
            Route::post('/action', [MerchantController::class, "action"])
                ->middleware('role_or_permission:admin|merchant-action')
                ->name('dashboard.merchant.action');
            Route::get('/supervisors', [MerchantController::class, "supervisors"])->name('merchant.supervisors');
            Route::get('/vehicles/{id}', [MerchantController::class, "vehicles"])
                ->middleware('role_or_permission:admin|merchant-vehicles|vehicle-list|vehicle-manage')
                ->name('dashboard.merchant.vehicles');
            Route::get('/suggest', [MerchantController::class, "suggest"])->name('dashboard.merchant.suggest');
            Route::post('/vehicle-stats/{id}', [MerchantController::class, "vehicleStatistics"])
                ->middleware('role_or_permission:admin|merchant-statistics|merchants-statistics')
                ->name('merchant.dashboard.vehicleStat');
            Route::post('/route-stats', [MerchantController::class, "routeStatistics"])
                ->middleware('role_or_permission:admin|merchant-statistics|merchants-statistics')
                ->name('merchant.dashboard.routeStat');
            Route::post('/schedule-stats', [MerchantController::class, "scheduleStatistics"])
                ->middleware('role_or_permission:admin|merchant-statistics|merchants-statistics')
                ->name('merchant.dashboard.scheduleStat');

            Route::get('/bookings/{id}', [DashboardController::class, "merchantBookings"])
                ->middleware('role_or_permission:admin|merchant-bookings|booking-list|bookings-list')
                ->name('dashboard.merchant.bookings');
        });

        Route::prefix('vehicle')->group(function () {
            Route::get('/', [VehicleController::class, "index"])
                ->middleware('role_or_permission:admin|vehicle-list|vehicle-manage')
                ->name('dashboard.vehicle.index');
            Route::get('/create/{id?}', [VehicleController::class, "create"])
                ->middleware('role_or_permission:admin|vehicle-create|vehicle-add')
                ->name('dashboard.vehicle.create');
            Route::post('/store', [VehicleController::class, "store"])
                ->middleware('role_or_permission:admin|vehicle-create|vehicle-add')
                ->name('dashboard.vehicle.store');
            Route::get('/edit/{id}', [VehicleController::class, "edit"])
                ->middleware('role_or_permission:admin|vehicle-edit|vehicle-update')
                ->name('dashboard.vehicle.edit');
            Route::put('/update/{id}', [VehicleController::class, "update"])
                ->middleware('role_or_permission:admin|vehicle-edit|vehicle-update')
                ->name('dashboard.vehicle.update');
            Route::get('/show/{id}', [VehicleController::class, "show"])
                ->middleware('role_or_permission:admin|vehicle-show|vehicle-view')
                ->name('dashboard.vehicle.show');
            Route::get('/routes/{id}', [VehicleController::class, "routes"])
                ->middleware('role_or_permission:admin|route-list|routes-list')
                ->name('dashboard.vehicle.routes');
            Route::get('/schedule/{id}', [VehicleController::class, "schedules"])
                ->middleware('role_or_permission:admin|schedule-list|schedule-manage')
                ->name('dashboard.vehicle.schedules');
            Route::get('/cabins/{id?}', [VehicleController::class, "cabins"])
                ->middleware('role_or_permission:admin|cabin-list|cabins-list')
                ->name('dashboard.vehicle.cabins');
            Route::get('/suggest', [VehicleController::class, "suggest"])->name('dashboard.vehicle.suggest');
            Route::post('/stats/{id}', [VehicleController::class, "scheduleStatistics"])
                ->middleware('role_or_permission:admin|vehicle-statistics|vehicles-statistics')
                ->name('dashboard.vehicle.scheduleStat');
            Route::post('/stat-chart/{id}', [VehicleController::class, "scheduleChart"])
                ->middleware('role_or_permission:admin|vehicle-statistics|vehicles-statistics')
                ->name('dashboard.vehicle.scheduleChart');
            Route::post('/officer-report/{id}', [VehicleController::class, "officerStatistics"])->name('dashboard.vehicle.officerReport');
            Route::post('/add-supervisor', [VehicleController::class, "assignSupervisor"])
                ->middleware('role_or_permission:admin|supervisor-assign')
                ->name('dashboard.vehicle.assignSupervisor');
            Route::get('/bookings/{id}', [VehicleController::class, "bookings"])
                ->middleware('role_or_permission:admin|booking-list|bookings-list')
                ->name('dashboard.vehicle.bookings');
            Route::get('/deck-fares/{id}', [VehicleController::class, "deckFares"])
                ->middleware('role_or_permission:admin|vehicle-deckfare|deckfare-list|deckfare-create')
                ->name('dashboard.vehicle.deckfares');
            Route::post('/action', [VehicleController::class, "action"])
                ->middleware('role_or_permission:admin|vehicle-action|vehicle-actions')
                ->name('dashboard.vehicle.action');

            Route::prefix('coupon')->group(function () {
                Route::get('/', [CouponController::class, "index"])
                    ->middleware('role_or_permission:admin|coupon-list')
                    ->name('dashboard.coupon.index');
                Route::get('/create', [CouponController::class, "create"])
                    ->middleware('role_or_permission:admin|coupon-create')
                    ->name('dashboard.coupon.create');
                Route::post('/store', [CouponController::class, "store"])
                    ->middleware('role_or_permission:admin|coupon-create')
                    ->name('dashboard.coupon.store');
                Route::get('/edit/{id}', [CouponController::class, "edit"])
                    ->middleware('role_or_permission:admin|coupon-edit')
                    ->name('dashboard.coupon.edit');
                Route::put('/update/{id}', [CouponController::class, "update"])
                    ->middleware('role_or_permission:admin|coupon-edit')
                    ->name('dashboard.coupon.update');
                Route::post('/broadcust', [CouponController::class, "broadcust"])
                    ->middleware('role_or_permission:admin|coupon-broadcust')
                    ->name('dashboard.coupon.broadcust');
                Route::post('/action', [CouponController::class, "action"])
                    ->middleware('role_or_permission:admin|coupon-action')
                    ->name('dashboard.coupon.action');
            });

            Route::prefix('discount')->group(function () {
                Route::get('/', [DiscountController::class, "index"])
                    ->middleware('role_or_permission:admin|discount-list')
                    ->name('dashboard.discount.index');
                Route::get('create', [DiscountController::class, "create"])
                    ->middleware('role_or_permission:admin|discount-create')
                    ->name('dashboard.discount.create');
                Route::post('store', [DiscountController::class, "store"])
                    ->middleware('role_or_permission:admin|discount-create')
                    ->name('dashboard.discount.store');
                Route::get('edit/{id}', [DiscountController::class, "edit"])
                    ->middleware('role_or_permission:admin|discount-edit')
                    ->name('dashboard.discount.edit');
                Route::put('update/{id}', [DiscountController::class, "update"])
                    ->middleware('role_or_permission:admin|discount-edit')
                    ->name('dashboard.discount.update');
                Route::get('test', [DiscountController::class, "suggest"])->name('dashboard.discount.suggest');
                Route::post('action', [DiscountController::class, "action"])
                    ->middleware('role_or_permission:admin|discount-action')
                    ->name('dashboard.discount.action');
            });

            Route::prefix('banner')->group(function () {
                Route::get('/', [BannerController::class, "index"])
                    ->middleware('role_or_permission:admin|banner-list')
                    ->name('dashboard.banner.index');
                Route::get('/create', [BannerController::class, "create"])
                    ->middleware('role_or_permission:admin|banner-create')
                    ->name('dashboard.banner.create');
                Route::post('/store', [BannerController::class, "store"])
                    ->middleware('role_or_permission:admin|banner-create')
                    ->name('dashboard.banner.store');
                Route::get('/edit/{id}', [BannerController::class, "edit"])
                    ->middleware('role_or_permission:admin|banner-edit')
                    ->name('dashboard.banner.edit');
                Route::put('/update/{id}', [BannerController::class, "update"])
                    ->middleware('role_or_permission:admin|banner-edit')
                    ->name('dashboard.banner.update');
                Route::post('/action', [BannerController::class, "action"])
                    ->middleware('role_or_permission:admin|banner-action')
                    ->name('dashboard.banner.action');
            });

            Route::prefix('social')->group(function () {
                Route::get('/', [SocialPosterController::class, "index"])
                    ->middleware('role_or_permission:admin|social-list')
                    ->name('dashboard.social.index');
                Route::get('/create', [SocialPosterController::class, "create"])
                    ->middleware('role_or_permission:admin|social-create')
                    ->name('dashboard.social.create');
                Route::post('/store', [SocialPosterController::class, "store"])
                    ->middleware('role_or_permission:admin|social-create')
                    ->name('dashboard.social.store');
                Route::get('/edit/{id}', [SocialPosterController::class, "edit"])
                    ->middleware('role_or_permission:admin|social-edit')
                    ->name('dashboard.social.edit');
                Route::put('/update/{id}', [SocialPosterController::class, "update"])
                    ->middleware('role_or_permission:admin|social-edit')
                    ->name('dashboard.social.update');
                Route::post('/action', [SocialPosterController::class, "action"])
                    ->middleware('role_or_permission:admin|social-action')
                    ->name('dashboard.social.action');
            });

            Route::prefix('cabin')->group(function () {
                Route::post('/batch-upload', [VehicleCabinController::class, "batchStore"])->name('dashboard.vehicle.cabin.batch');
            });

            Route::prefix('supervisor')->group(function () {
                Route::post('/assign', [SupervisorController::class, "assignToVehicle"])
                    ->middleware('role_or_permission:admin|supervisor-assign|supervisors-assign')
                    ->name('dashboard.supervisor.assign');
            });

            Route::prefix('route')->group(function () {
                Route::get('/', [VehicleRouteController::class, "index"])
                    ->middleware('role_or_permission:admin|route-list')
                    ->name('dashboard.routes.index');
                Route::get('/create', [VehicleRouteController::class, "create"])
                    ->middleware('role_or_permission:admin|route-create')
                    ->name('dashboard.routes.create');
                Route::post('/store', [VehicleRouteController::class, "store"])
                    ->middleware('role_or_permission:admin|route-create')
                    ->name('dashboard.routes.store');
                Route::get('/edit/{id}', [VehicleRouteController::class, "edit"])
                    ->middleware('role_or_permission:admin|route-edit')
                    ->name('dashboard.routes.edit');
                Route::put('/update/{id}', [VehicleRouteController::class, "update"])
                    ->middleware('role_or_permission:admin|route-edit')
                    ->name('dashboard.routes.update');
                Route::get('/show/{id}', [VehicleRouteController::class, "show"])
                    ->middleware('role_or_permission:admin|route-show')
                    ->name('dashboard.routes.show');
                Route::get('/suggest', [VehicleRouteController::class, "suggest"])->name('dashboard.routes.suggest');
                Route::post('/properties', [VehicleRouteController::class, "properties"])->name('dashboard.route.properties');
                Route::get('/naming', [VehicleRouteController::class, "naming"])->name('dashboard.route.name');
                Route::post('/action', [VehicleRouteController::class, "action"])
                    ->middleware('role_or_permission:admin|route-action')
                    ->name('dashboard.route.action');
            });

            Route::prefix('ghat')->group(function () {
                Route::get('/', [GhatController::class, "index"])
                    ->middleware('role_or_permission:admin|ghat-list')
                    ->name('dashboard.ghat.index');
                Route::get('/create', [GhatController::class, "create"])
                    ->middleware('role_or_permission:admin|ghat-create')
                    ->name('dashboard.ghat.create');
                Route::post('/store', [GhatController::class, "store"])
                    ->middleware('role_or_permission:admin|ghat-create')
                    ->name('dashboard.ghat.store');
                Route::get('/edit/{id}', [GhatController::class, "edit"])
                    ->middleware('role_or_permission:admin|ghat-edit')
                    ->name('dashboard.ghat.edit');
                Route::put('/update/{id}', [GhatController::class, "update"])
                    ->middleware('role_or_permission:admin|ghat-edit')
                    ->name('dashboard.ghat.update');
                Route::post('/action', [GhatController::class, "action"])
                    ->middleware('role_or_permission:admin|ghat-delete')
                    ->name('dashboard.ghat.action');
                Route::get('suggest', [GhatController::class, "suggest"])->name('dashboard.ghat.suggest');
            });

            Route::prefix('cabin')->group(function () {
                Route::post('/store', [VehicleCabinController::class, "store"])
                    ->middleware('role_or_permission:admin|cabin-create|cabins-add')
                    ->name('dashboard.cabin.store');
                Route::get('/edit/{id}', [VehicleCabinController::class, "edit"])
                    ->middleware('role_or_permission:admin|cabin-edit|cabins-update')
                    ->name('dashboard.cabin.edit');
                Route::put('/update/{id}', [VehicleCabinController::class, "update"])
                    ->middleware('role_or_permission:admin|cabin-edit|cabins-update')
                    ->name('dashboard.cabin.update');

                Route::prefix('type')->group(function () {
                    Route::get('/', [CabinTypeController::class, "index"])
                        ->middleware('role_or_permission:admin|type-list')
                        ->name('dashboard.cabintype.index');
                    Route::get('/create/{id?}', [CabinTypeController::class, "create"])
                        ->middleware('role_or_permission:admin|type-create|type-add')
                        ->name('dashboard.cabintype.create');
                    Route::post('/store', [CabinTypeController::class, "store"])
                        ->middleware('role_or_permission:admin|type-create|type-add')
                        ->name('dashboard.cabintype.store');
                    Route::get('/edit/{id}', [CabinTypeController::class, "edit"])
                        ->middleware('role_or_permission:admin|type-edit|type-update')
                        ->name('dashboard.cabintype.edit');
                    Route::put('/update/{id}', [CabinTypeController::class, "update"])
                        ->middleware('role_or_permission:admin|type-edit|type-update')
                        ->name('dashboard.cabintype.update');
                    Route::get('/show/{id}', [CabinTypeController::class, "show"])
                        ->middleware('role_or_permission:admin|type-show')
                        ->name('dashboard.cabintype.show');
                });
            });

            Route::prefix('schedule')->group(function () {
                Route::get('/', [VehicleScheduleController::class, "index"])
                    ->middleware('role_or_permission:admin|schedule-list|schedule-manage')
                    ->name('dashboard.schedule.index');
                Route::get('/create', [VehicleScheduleController::class, "create"])
                    ->middleware('role_or_permission:admin|schedule-create|schedule-assign')
                    ->name('dashboard.schedule.create');
                Route::post('store', [VehicleScheduleController::class, "store"])
                    ->middleware('role_or_permission:admin|schedule-create|schedule-assign')
                    ->name('dashboard.schedule.store');
                Route::get('show/{id}', [VehicleScheduleController::class, "show"])
                    ->middleware('role_or_permission:admin|schedule-show|schedule-view')
                    ->name('dashboard.schedule.show');
                Route::get('/transfer-quota/{id}', [ScheduleCabinMappingsController::class, "transferQuota"])->name('dashboard.schedule.transferquota');
                Route::put('/transfer-quota/{id}', [ScheduleCabinMappingsController::class, "transferQuotaUpdate"])->name('dashboard.schedule.quotatransfer');
                Route::put('/batch-update/{id}', [ScheduleCabinMappingsController::class, "batchUpdate"])
                    ->middleware('role_or_permission:admin|merchant|schedule-batch-update')
                    ->name('dashboard.schedule.batchupdate');
                Route::get('/export-mapping/{id}', [ScheduleCabinMappingsController::class, "exportMapping"])
                    ->middleware('role_or_permission:admin|schedule-export-mapping')
                    ->name('dashboard.schedule.exportmapping');
                Route::put('/extend/{schedule}', [VehicleScheduleController::class, "extendOperationHour"])->name('dashboard.schedule.extend');
                Route::get('/report/{schedule}', [VehicleScheduleController::class, "report"])->name('dashboard.schedule.report');
                Route::get('/export/{schedule}', [VehicleScheduleController::class, "reportExport"])->name('dashboard.schedule.report.export');
                Route::get('/cabins/{id}', [VehicleScheduleController::class, "cabins"])->name('dashboard.schedule.cabins');
                Route::get('/trip/list', [VehicleScheduleController::class, "suggestions"])->name('dashboard.schedule.list');
                Route::get('/bookings/{id}', [VehicleScheduleController::class, "bookings"])->name('dashboard.schedule.bookings');

                Route::get('/cancel/{id}/{vehicleId}', [VehicleScheduleController::class, "cancel"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.cancel');
                Route::get('/cancel-confirm/{id}', [VehicleScheduleController::class, "cancelConfirm"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.cancelConfirm');

                Route::get('/reschedule/{id}/{vehicleId}', [VehicleScheduleController::class, "reschedule"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.reschedule');
                Route::post('/reschedule-confirm', [VehicleScheduleController::class, "rescheduleConfirm"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.rescheduleConfirm');
                Route::get('/cabins/{id}', [VehicleScheduleController::class, "cabins"])->name('dashboard.schedule.cabins');
                Route::post('/honorium', [VehicleScheduleController::class, "honorium"])
                    ->middleware('role_or_permission:admin|booking-assign-honorium')
                    ->name('dashboard.schedule.honorium');
                Route::post('/pause', [VehicleScheduleController::class, "pauseSchedules"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.pause');
                Route::post('/action', [VehicleScheduleController::class, "action"])
                    ->middleware('role_or_permission:admin|schedule-action')
                    ->name('dashboard.schedule.action');

                Route::prefix('mapping')->group(function () {
                    Route::get('/edit/{mapping}', [ScheduleCabinMappingsController::class, "edit"])
                        ->name('dashboard.mapping.edit');
                    Route::put('/update/{mapping}', [ScheduleCabinMappingsController::class, "update"])
                        ->name('dashboard.mapping.update');
                    Route::post('/action', [ScheduleCabinMappingsController::class, "action"])
                        ->name('dashboard.schedule.mapping.action');
                    Route::get('/book/{id}', [ScheduleCabinMappingsController::class, "bookNow"])
                        ->name('dashboard.schedule.mapping.book');
                });
            });

            Route::prefix('fares')->group(function () {
                Route::post('/store', [DeckFareController::class, "store"])
                    ->middleware('role_or_permission:admin|cabin-create|cabins-add')
                    ->name('dashboard.deckfare.store');
                Route::get('/edit/{id}', [DeckFareController::class, "edit"])
                    ->middleware('role_or_permission:admin|cabin-create|cabins-add')
                    ->name('dashboard.deckfare.edit');
                Route::put('/update/{id}', [DeckFareController::class, "update"])
                    ->middleware('role_or_permission:admin|cabin-edit|cabins-update')
                    ->name('dashboard.deckfare.update');
                Route::delete('/destroy', [DeckFareController::class, "destroy"])
                    ->middleware('role_or_permission:admin|cabin-delete')
                    ->name('dashboard.deckfare.delete');
            });
        });

        Route::prefix('booking')->group(function () {
            Route::get('/', [BookingController::class, "index"])
                ->middleware('role_or_permission:admin|booking-list|bookings-list')
                ->name('dashboard.booking.index');
            Route::get('/create', [BookingController::class, "create"])
                ->middleware('role_or_permission:admin|booking-quick|other-quick-book')
                ->name('dashboard.booking.create');
            Route::post('/store', [BookingController::class, "store"])
                ->middleware('role_or_permission:admin|booking-quick|other-quick-book')
                ->name('dashboard.booking.store');
            Route::get('/edit/{id}', [BookingController::class, "edit"])
                ->middleware('role_or_permission:admin|booking-quick|other-quick-book')
                ->name('dashboard.booking.edit');
            Route::put('/update/{id}', [BookingController::class, "update"])
                ->middleware('role_or_permission:admin|booking-quick|other-quick-book')
                ->name('dashboard.booking.update');
            Route::put('/failed/confirm/{id}', [BookingController::class, "paymentConfirm"])
                ->name('dashboard.booking.failed.confirm');
            Route::get('/show/{id}', [BookingController::class, "show"])
                ->middleware('role_or_permission:admin|booking-view|bookings-overview')
                ->name('dashboard.booking.show');
            Route::post('/cancel/batch', [BookingController::class, "batchCancel"])->name('booking.cancel.batch');
            Route::get('/invoice/{id}', [BookingController::class, "invoice"])
                ->middleware('role_or_permission:admin|booking-view|bookings-overview')
                ->name('dashboard.booking.invoice');
            Route::post('/summary', [BookingController::class, "summary"])->name('dashboard.booking.summary');
            Route::get('/invoice/print/{id}', [BookingController::class, "printInvoice"])
                ->middleware('role_or_permission:admin|booking-view|bookings-overview')
                ->name('dashboard.booking.invoice.print');
            Route::post('/invoice/send', [BookingController::class, "sendInvoice"])
                ->middleware('role_or_permission:admin|booking-view|bookings-overview')
                ->name('dashboard.booking.invoice.send');

            Route::get('/invoice/view/{id}', [BookingController::class, "ViewInvoice"])->name('invoice.view');

            Route::prefix('cancellation')->group(function () {
                Route::get('/', [BookingCancellationController::class, "index"])
                    ->middleware('role_or_permission:admin|cancellation-list')
                    ->name('dashboard.cancellation.index');
                Route::get('/show/{id}', [BookingCancellationController::class, "show"])
                    ->middleware('role_or_permission:admin|cancellation-show')
                    ->name('dashboard.cancellation.show');
                Route::post('/store', [BookingCancellationController::class, "store"])
                    ->middleware('role_or_permission:admin|cancellation-action')
                    ->name('dashboard.cancellation.store');
                Route::post('/cancellation/action', [BookingCancellationController::class, "action"])
                    ->middleware('role_or_permission:admin|cancellation-action')
                    ->name('dashboard.cancellation.action');
            });

            Route::get('/export', [PaymentController::class, "export"])->name('payment.export');
//            Route::resource('payment', 'PaymentController', ["as" => "dashboard"]);
        });

        Route::prefix('page')->group(function () {
            Route::get('/', [PageController::class, "index"])
                ->middleware('role_or_permission:admin|page-list')
                ->name('dashboard.page.index');
            Route::get('/create', [PageController::class, "create"])
                ->middleware('role_or_permission:admin|page-create')
                ->name('dashboard.page.create');
            Route::post('/store', [PageController::class, "store"])
                ->middleware('role_or_permission:admin|page-create')
                ->name('dashboard.page.store');
            Route::get('/edit/{id}', [PageController::class, "edit"])
                ->middleware('role_or_permission:admin|page-edit')
                ->name('dashboard.page.edit');
            Route::post('/update/{id}', [PageController::class, "update"])
                ->middleware('role_or_permission:admin|page-edit')
                ->name('dashboard.page.update');
            Route::delete('/delete/{id}', [PageController::class, "destroy"])
                ->middleware('role_or_permission:admin|page-delete')
                ->name('dashboard.page.delete');
        });

        Route::prefix('blog')->group(function () {
            Route::get('/', [BlogController::class, "index"])
                ->middleware('role_or_permission:admin|blog-list')
                ->name('dashboard.blog.index');
            Route::get('/create', [BlogController::class, "create"])
                ->middleware('role_or_permission:admin|blog-create')
                ->name('dashboard.blog.create');
            Route::post('/store', [BlogController::class, "store"])
                ->middleware('role_or_permission:admin|blog-create')
                ->name('dashboard.blog.store');
            Route::get('/edit/{id}', [BlogController::class, "edit"])
                ->middleware('role_or_permission:admin|blog-edit')
                ->name('dashboard.blog.edit');
            Route::post('/update/{id}', [BlogController::class, "update"])
                ->middleware('role_or_permission:admin|blog-edit')
                ->name('dashboard.blog.update');

            Route::prefix('catagory')->group(function () {
                Route::get('/', [BlogCatagoryController::class, "index"])
                    ->middleware('role_or_permission:admin|category-list')
                    ->name('dashboard.blogcatagory.index');
                Route::get('/create', [BlogCatagoryController::class, "create"])
                    ->middleware('role_or_permission:admin|blog-create')
                    ->name('dashboard.blogcatagory.create');
                Route::post('/store', [BlogCatagoryController::class, "store"])
                    ->middleware('role_or_permission:admin|blog-create')
                    ->name('dashboard.blogcatagory.store');
                Route::get('/edit/{id}', [BlogCatagoryController::class, "edit"])
                    ->middleware('role_or_permission:admin|blog-edit')
                    ->name('dashboard.blogcatagory.edit');
                Route::post('/update/{id}', [BlogCatagoryController::class, "update"])
                    ->middleware('role_or_permission:admin|blog-edit')
                    ->name('dashboard.blogcatagory.update');
            });
        });

//        Route::prefix('report')->group(function () {
//            Route::get('/', [ReportController::class, "index"])->name('dashboard.report.index');
//            Route::get('/daily-booking', [ReportController::class, "dailyBookings"])->name('dashboard.report.booking.daily');
//            Route::get('/daily-vehicle-booking', [ReportController::class, "dailyVehicleBookings"])->name('dashboard.report.vehicle.booking');
//            Route::post('/daily-vehicle-booking', [ReportController::class, "exportDailyVehicleBookings"])->name('dashboard.report.vehicle.export');
//            Route::get('/daily-trip-report', [ReportController::class, "dailyTripReport"])->name('dashboard.report.trip');
//            Route::post('/daily-trip-report-export', [ReportController::class, "exportTripReport"])->name('dashboard.report.trip.export');
//        });

        Route::prefix('setting')->group(function () {
            Route::prefix('user')->group(function () {
                Route::get('/', [UserController::class, "index"])
                    ->middleware('role_or_permission:admin|user-list|supervisor-add')
                    ->name('dashboard.user.index');
                Route::get('/create', [UserController::class, "create"])
                    ->middleware('role_or_permission:admin|user-create|supervisor-add')
                    ->name('dashboard.user.create');
                Route::post('/store', [UserController::class, "store"])
                    ->middleware('role_or_permission:admin|user-create|supervisor-add')
                    ->name('dashboard.user.store');
                Route::get('/show/{id}', [UserController::class, "show"])
                    ->middleware('role_or_permission:admin|user-show|supervisor-view')
                    ->name('dashboard.user.show');
                Route::get('/edit/{id}', [UserController::class, "edit"])
                    ->middleware('role_or_permission:admin|user-edit|supervisor-edit')
                    ->name('dashboard.user.edit');
                Route::put('/update/{id}', [UserController::class, "update"])
                    ->middleware('role_or_permission:admin|user-edit|supervisor-edit')
                    ->name('dashboard.user.update');
                Route::get('/export', [UserController::class, "index"])->name('dashboard.user.export');
                Route::get('/import', [UserController::class, "index"])->name('dashboard.user.import');
                Route::post('/action', [UserController::class, "action"])
                    ->middleware('role_or_permission:admin|user-action|supervisor-suspend')
                    ->name('dashboard.user.action');
                Route::post('/change/password', [UserController::class, "changePassword"])->name('dashboard.user.password');
                Route::get('/profile', [UserController::class, "profile"])->name('dashboard.user.profile');
                Route::post('/upload', [UserController::class, "upload"])->name('dashboard.user.upload');
            });

            Route::prefix('designation')->group(function () {
                Route::get('/', [DesignationController::class, "index"])->name('dashboard.designation.index');
                Route::get('/create', [DesignationController::class, "create"])->name('dashboard.designation.create');
                Route::post('/store', [DesignationController::class, "store"])->name('dashboard.designation.store');
                Route::get('/edit/{id}', [DesignationController::class, "edit"])->name('dashboard.designation.edit');
                Route::put('/update/{id}', [DesignationController::class, "update"])->name('dashboard.designation.update');
            });

            Route::prefix('role')->group(function () {
                Route::get('/', [RoleController::class, "index"])
                    ->middleware('role:admin')
                    ->name('dashboard.role.index');
                Route::get('/create', [RoleController::class, "create"])
                    ->middleware('role:admin')
                    ->name('dashboard.role.create');
                Route::post('/store', [RoleController::class, "store"])
                    ->middleware('role:admin')
                    ->name('dashboard.role.store');
                Route::get('/edit/{id}', [RoleController::class, "edit"])
                    ->middleware('role:admin')
                    ->name('dashboard.role.edit');
                Route::put('/update/{id}', [RoleController::class, "update"])
                    ->middleware('role:admin')
                    ->name('dashboard.role.update');
            });

            Route::prefix('sponsor')->group(function () {
                Route::get('/', [SponsorController::class, "index"])
                    ->middleware('role_or_permission:admin|sponsor-list')
                    ->name('dashboard.sponsor.index');
                Route::get('/create', [SponsorController::class, "create"])
                    ->middleware('role_or_permission:admin|sponsor-create')
                    ->name('dashboard.sponsor.create');
                Route::post('/store', [SponsorController::class, "store"])
                    ->middleware('role_or_permission:admin|sponsor-create')
                    ->name('dashboard.sponsor.store');
                Route::get('/edit/{id}', [SponsorController::class, "edit"])
                    ->middleware('role_or_permission:admin|sponsor-edit')
                    ->name('dashboard.sponsor.edit');
                Route::put('/update/{id}', [SponsorController::class, "update"])
                    ->middleware('role_or_permission:admin|sponsor-edit')
                    ->name('dashboard.sponsor.update');
                Route::delete('/delete/{id}', [SponsorController::class, "update"])
                    ->middleware('role_or_permission:admin|sponsor-action')
                    ->name('dashboard.sponsor.delete');
                Route::post('/action', [SponsorController::class, "action"])
                    ->middleware('role_or_permission:admin|sponsor-action')
                    ->name('dashboard.sponsor.action');
            });

            Route::prefix('permission')->group(function () {
                Route::get('/', [PermissionController::class, "index"])
                    ->middleware('role_or_permission:admin|permission-list|permissions-list')
                    ->name('dashboard.permission.index');
            });

            Route::prefix('option')->group(function () {
                Route::get('/', [OptionController::class, "index"])
                    ->middleware('role_or_permission:admin|settings-manage|setting-manage')
                    ->name('dashboard.option.index');
                Route::post('/store', [OptionController::class, "store"])
                    ->middleware('role_or_permission:admin|settings-manage|setting-manage')
                    ->name('dashboard.option.store');
            });

//            Route::resource('gateway', 'GatewayController');
        });
    });

    Route::middleware(['auth'])->prefix('merchant')->group(function () {
        Route::prefix('report')->group(function () {
            Route::get('/', [MerchantReportController::class, "index"])->name('merchant.report.index');
            Route::get('/statistics', [MerchantReportController::class, "statistics"])->name('merchant.report.statistics');
        });
//        Route::prefix('accounts')->group(function () {
//            Route::get('/', [AccountsController::class, "index"])->name('dashboard.accounts.index');
//        });
//
//        Route::prefix('inventory')->group(function () {
//            Route::get('/', [AccountsController::class, "index"])->name('dashboard.inventory.index');
//        });
    });
});
