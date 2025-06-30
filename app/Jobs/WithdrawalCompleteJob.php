<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AgentCommission;
use App\Models\AgentWithdrawal;

class WithdrawalCompleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var AgentWithdrawal
     */
    private $withdrawal;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(AgentWithdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        AgentCommission::create([
            'user_id' => $this->withdrawal->user_id,
            'type' => 'debit',
            'amount' => $this->withdrawal->amount,
            'total_sale' => $this->withdrawal->balance,
            'commission_date' => $this->withdrawal->created_at->format('Y-m-d'),
            'purpose' => 'withdrawal'
        ]);
    }
}
