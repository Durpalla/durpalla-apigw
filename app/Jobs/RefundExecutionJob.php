<?php

namespace App\Jobs;

use App\Models\BookingCancellation;
use App\Services\CustomerRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefundExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $cancellationId)
    {
    }

    public function handle(CustomerRefundService $refunds): void
    {
        $cancellation = BookingCancellation::query()->find($this->cancellationId);
        if (! $cancellation) {
            return;
        }

        $refunds->execute($cancellation);
    }
}
