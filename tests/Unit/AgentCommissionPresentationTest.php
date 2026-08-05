<?php

namespace Tests\Unit;

use App\Models\AgentCommission;
use App\Models\AgentCommissionAccrual;
use App\Models\Booking;
use App\Services\CalculationService;
use App\Support\AgentApiPresenter;
use ReflectionProperty;
use Tests\TestCase;

class AgentCommissionPresentationTest extends TestCase
{
    public function test_service_charge_one_hundred_at_twenty_percent_is_twenty_regardless_of_fare(): void
    {
        $service = new CalculationService;
        $format = new ReflectionProperty($service, 'numberFormat');
        $format->setValue($service, 'actualFormat');

        $amount = $service->calculateAgentCommission([
            'price' => 9999,
            'charge_type' => 'fixed',
            'charge_amount' => 100,
            'incentive_type' => 'percent',
            'incentive' => 20,
        ]);

        $this->assertSame('20.00', $amount);
    }

    public function test_presenter_distinguishes_pending_referral_and_reversal(): void
    {
        $booking = new Booking;
        $booking->id = 42;

        $accrual = new AgentCommissionAccrual([
            'agent_id' => 7,
            'booking_id' => 42,
            'source_type' => 'hotel_reservation',
            'source_id' => 9,
            'source_key' => 'hotel:hotel_reservation:9',
            'kind' => 'referral',
            'service_type' => 'hotel',
            'base_amount' => 100,
            'rate' => 20,
            'incentive_type' => 'percent',
            'amount' => 20,
            'eligible_at' => '2026-08-05 23:59:59',
            'status' => 'pending',
        ]);
        $accrual->id = 9;
        $accrual->setRelation('booking', $booking);
        $accrual->setRelation('bookingItem', null);

        $pending = AgentApiPresenter::pendingAccrual($accrual);
        $this->assertSame('PENDING', $pending['status']);
        $this->assertSame('referral', $pending['kind']);
        $this->assertSame('hotel', $pending['serviceType']);
        $this->assertSame(20.0, $pending['commissionAmount']);

        $commission = new AgentCommission([
            'type' => 'debit',
            'purpose' => 'cancellation',
            'total_sale' => 100,
            'amount' => 20,
        ]);
        $commission->setRelation('bookingItem', null);
        $commission->setRelation('accrual', $accrual);

        $reversal = AgentApiPresenter::commission($commission);
        $this->assertSame('REVERSED', $reversal['status']);
        $this->assertSame(-20.0, $reversal['commissionAmount']);
        $this->assertSame('referral', $reversal['kind']);
    }
}
