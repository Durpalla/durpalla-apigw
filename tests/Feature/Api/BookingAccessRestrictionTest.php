<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ResolveApiPartner;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ensures booking detail endpoints cannot be used to enumerate/access another
 * actor's booking by numeric id.
 */
class BookingAccessRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function test_customer_cannot_access_another_customers_booking(): void
    {
        $owner = $this->makeCustomer('01710000001');
        $intruder = $this->makeCustomer('01710000002');

        $ownBooking = $this->makeBooking(['customer_id' => $owner->id]);
        $foreignBooking = $this->makeBooking(['customer_id' => $intruder->id]);

        $own = $this->actingAs($owner, 'customer')
            ->getJson('/api/v1/my/booking/'.$ownBooking->id);
        $this->assertNotSame(404, $own->getStatusCode(), 'Owner should resolve their own booking');
        if ($own->getStatusCode() === 200) {
            $own->assertJsonPath('success', true)
                ->assertJsonPath('booking.id', $ownBooking->id);
        }

        $this->actingAs($owner, 'customer')
            ->getJson('/api/v1/my/booking/'.$foreignBooking->id)
            ->assertNotFound();

        $this->actingAs($owner, 'customer')
            ->getJson('/api/v1/my/booking/android/'.$foreignBooking->id)
            ->assertNotFound();
    }

    public function test_agent_cannot_access_another_agents_booking(): void
    {
        $owner = $this->makeAgent('01720000001');
        $intruder = $this->makeAgent('01720000002');

        $ownBooking = $this->makeBooking([
            'customer_id' => $this->makeCustomer('01720000011')->id,
            'booked_by_type' => Agent::class,
            'booked_by_id' => $owner->id,
            'platform' => 'counter',
        ]);
        $foreignBooking = $this->makeBooking([
            'customer_id' => $this->makeCustomer('01720000012')->id,
            'booked_by_type' => Agent::class,
            'booked_by_id' => $intruder->id,
            'platform' => 'counter',
        ]);

        $own = $this->actingAs($owner, 'agent')
            ->getJson('/api/v1/agent/my/bookings/'.$ownBooking->id);
        $this->assertNotSame(404, $own->getStatusCode(), 'Owner agent should resolve their own booking');
        if ($own->getStatusCode() === 200) {
            $own->assertJsonPath('success', true);
        }

        $this->actingAs($owner, 'agent')
            ->getJson('/api/v1/agent/my/bookings/'.$foreignBooking->id)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->actingAs($owner, 'agent')
            ->postJson('/api/v1/agent/my/bookings/'.$foreignBooking->id.'/cancel', [])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_reseller_cannot_access_another_partys_booking(): void
    {
        $ownerParty = $this->makeApiPartner('owner-reseller');
        $intruderParty = $this->makeApiPartner('intruder-reseller');

        $ownBooking = $this->makeBooking([
            'customer_id' => $this->makeCustomer('01730000001')->id,
            'party_id' => $ownerParty->id,
            'booking_party' => 'other',
            'platform' => 'web',
        ]);
        $foreignBooking = $this->makeBooking([
            'customer_id' => $this->makeCustomer('01730000002')->id,
            'party_id' => $intruderParty->id,
            'booking_party' => 'other',
            'platform' => 'web',
        ]);

        $this->actingAsApiPartner($ownerParty)
            ->getJson('/api/reseller/v1/bookings/'.$ownBooking->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $ownBooking->id);

        $this->actingAsApiPartner($ownerParty)
            ->getJson('/api/reseller/v1/bookings/'.$foreignBooking->id)
            ->assertNotFound();
    }

    private function actingAsApiPartner(Party $party): self
    {
        // Skip Passport client-credentials; inject the resolved partner for ownership checks.
        $this->withoutMiddleware([
            \Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner::class,
        ]);

        $this->app->instance(ResolveApiPartner::class, new class($party)
        {
            public function __construct(private Party $party) {}

            public function handle($request, $next)
            {
                $request->attributes->set('api_partner', $this->party);
                app()->instance('api_partner', $this->party);

                return $next($request);
            }
        });

        return $this;
    }

    private function makeCustomer(string $mobile): Customer
    {
        $mobile = substr(preg_replace('/\D/', '', $mobile).random_int(1000, 9999), 0, 11);

        return Customer::query()->create([
            'name' => 'Customer '.$mobile,
            'email' => $mobile.'@example.com',
            'mobile' => $mobile,
            'password' => Hash::make('password123'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
    }

    private function makeAgent(string $mobile): Agent
    {
        $mobile = substr(preg_replace('/\D/', '', $mobile).random_int(1000, 9999), 0, 11);

        return Agent::query()->create([
            'name' => 'Agent '.$mobile,
            'email' => $mobile.'@example.com',
            'mobile' => $mobile,
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
    }

    private function makeApiPartner(string $slug): Party
    {
        $attrs = [
            'name' => 'Partner '.$slug,
            'slug' => $slug.'-'.uniqid(),
        ];
        if (Schema::hasColumn('parties', 'type')) {
            $attrs['type'] = Party::TYPE_API_PARTNER;
        }
        if (Schema::hasColumn('parties', 'status')) {
            $attrs['status'] = 1;
        }
        if (Schema::hasColumn('parties', 'email')) {
            $attrs['email'] = $slug.'@example.com';
        }
        if (Schema::hasColumn('parties', 'mobile')) {
            $attrs['mobile'] = '018'.substr(md5($slug.uniqid()), 0, 8);
        }
        if (Schema::hasColumn('parties', 'domain_name')) {
            $attrs['domain_name'] = $slug.'.example.test';
        }
        if (Schema::hasColumn('parties', 'officer_id')) {
            $attrs['officer_id'] = $this->ensureOfficerUserId();
        }

        return Party::query()->create($attrs);
    }

    private function ensureOfficerUserId(): int
    {
        $existing = User::query()->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $attrs = [
            'name' => 'Test Officer',
            'email' => 'officer+'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ];
        if (Schema::hasColumn('users', 'mobile')) {
            $attrs['mobile'] = '016'.random_int(10000000, 99999999);
        }
        if (Schema::hasColumn('users', 'status')) {
            $attrs['status'] = 1;
        }

        return (int) User::query()->create($attrs)->id;
    }

    private function makeBooking(array $overrides = []): Booking
    {
        $attrs = array_merge([
            'booking_date' => now()->toDateString(),
            'customer_id' => 1,
            'status' => 'COMPLETE',
            'total_amount' => 1000,
            'total_discount' => 0,
            'vat_amount' => 0,
            'vat_total' => 0,
            'charge_amount' => 0,
            'charge_total' => 0,
            'total_payable' => 1000,
            'payment_status' => 1,
            'platform' => 'web',
            'service_type' => 'transport',
        ], $overrides);

        // Tolerate partially-migrated apigw_test schemas during suite runs.
        $attrs = array_filter(
            $attrs,
            static fn ($value, $key) => Schema::hasColumn('bookings', $key),
            ARRAY_FILTER_USE_BOTH
        );

        $booking = Booking::query()->create($attrs);

        $paymentAttrs = [
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'transaction_id' => 'T'.$booking->id.uniqid(),
            'status' => 'success',
            'paid_amount' => 1000,
            'store_amount' => 1000,
            'dues' => 0,
        ];
        $paymentAttrs = array_filter(
            $paymentAttrs,
            static fn ($value, $key) => Schema::hasColumn('payments', $key),
            ARRAY_FILTER_USE_BOTH
        );
        Payment::query()->create($paymentAttrs);

        return $booking->fresh();
    }

    private function ensureSchema(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('mobile')->unique();
                $table->string('password');
                $table->unsignedTinyInteger('status')->default(1);
                $table->string('profile_pic')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->string('two_factor_method')->nullable();
                $table->text('two_factor_secret')->nullable();
                $table->rememberToken();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agents')) {
            Schema::create('agents', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('mobile')->unique();
                $table->string('password');
                $table->unsignedTinyInteger('status')->default(1);
                $table->string('device_id')->nullable();
                $table->string('profile_pic')->nullable();
                $table->rememberToken();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable()->unique();
                $table->string('type')->nullable();
                $table->string('email')->nullable();
                $table->string('mobile')->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->text('address')->nullable();
                $table->string('domain_name')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('officer_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        } else {
            foreach ([
                'type' => fn (Blueprint $t) => $t->string('type')->nullable(),
                'status' => fn (Blueprint $t) => $t->unsignedTinyInteger('status')->default(1),
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'mobile' => fn (Blueprint $t) => $t->string('mobile')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('parties', $column)) {
                    Schema::table('parties', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            }
        }

        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->string('pnr', 21)->nullable()->unique();
                $table->date('booking_date')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('booked_by_type')->nullable();
                $table->unsignedBigInteger('booked_by_id')->nullable();
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('vat_total', 12, 2)->default(0);
                $table->decimal('charge_amount', 12, 2)->default(0);
                $table->decimal('charge_total', 12, 2)->default(0);
                $table->string('booking_party')->nullable();
                $table->unsignedBigInteger('party_id')->nullable()->index();
                $table->string('status')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('total_discount', 12, 2)->default(0);
                $table->tinyInteger('payment_status')->default(0);
                $table->decimal('total_payable', 12, 2)->default(0);
                $table->string('platform')->nullable();
                $table->string('service_type')->nullable();
                $table->unsignedBigInteger('referring_agent_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        } else {
            foreach ([
                'pnr' => fn (Blueprint $t) => $t->string('pnr', 21)->nullable(),
                'booked_by_type' => fn (Blueprint $t) => $t->string('booked_by_type')->nullable(),
                'booked_by_id' => fn (Blueprint $t) => $t->unsignedBigInteger('booked_by_id')->nullable(),
                'party_id' => fn (Blueprint $t) => $t->unsignedBigInteger('party_id')->nullable(),
                'referring_agent_id' => fn (Blueprint $t) => $t->unsignedBigInteger('referring_agent_id')->nullable(),
                'platform' => fn (Blueprint $t) => $t->string('platform')->nullable(),
                'service_type' => fn (Blueprint $t) => $t->string('service_type')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('bookings', $column)) {
                    Schema::table('bookings', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            }
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('transaction_id')->nullable();
                $table->string('status')->nullable();
                $table->string('payment_gateway')->nullable();
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('store_amount', 12, 2)->default(0);
                $table->decimal('dues', 12, 2)->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_items')) {
            Schema::create('booking_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->decimal('price', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_cancellations')) {
            Schema::create('booking_cancellations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->index();
                $table->text('items')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('options')) {
            Schema::create('options', function (Blueprint $table) {
                $table->id();
                $table->string('field')->index();
                $table->text('value')->nullable();
                $table->string('tab')->nullable();
            });
        }

        if (! Schema::hasTable('agent_referred_merchants')) {
            Schema::create('agent_referred_merchants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->index();
                $table->unsignedBigInteger('merchant_id')->nullable()->index();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
}
