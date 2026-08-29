<?php

namespace App\Services\Telemetry;

class BusinessMetrics
{
    public static function recordPayment(string $gateway, string $status): void
    {
        if (! config('opentelemetry.enabled') || ! app()->bound('opentelemetry.meter')) {
            return;
        }

        static $counter = null;

        if ($counter === null) {
            $counter = app('opentelemetry.meter')
                ->createCounter('payment.operations', 'operations', 'Payment gateway operations');
        }

        $counter->add(1, [
            'gateway' => $gateway,
            'status' => $status,
            'service' => config('opentelemetry.service_name'),
        ]);
    }

    public static function recordBooking(string $serviceType, string $status): void
    {
        if (! config('opentelemetry.enabled') || ! app()->bound('opentelemetry.meter')) {
            return;
        }

        static $counter = null;

        if ($counter === null) {
            $counter = app('opentelemetry.meter')
                ->createCounter('booking.operations', 'operations', 'Booking operations');
        }

        $counter->add(1, [
            'service_type' => $serviceType,
            'status' => $status,
            'service' => config('opentelemetry.service_name'),
        ]);
    }

    public static function recordCircuitBreaker(string $service, string $state): void
    {
        if (! config('opentelemetry.enabled') || ! app()->bound('opentelemetry.meter')) {
            return;
        }

        static $counter = null;

        if ($counter === null) {
            $counter = app('opentelemetry.meter')
                ->createCounter('circuit_breaker.state', 'transitions', 'Circuit breaker state transitions');
        }

        $counter->add(1, [
            'service' => $service,
            'state' => $state,
        ]);
    }
}
