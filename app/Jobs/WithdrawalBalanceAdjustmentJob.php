<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Models\AgentWithdrawal;

class WithdrawalBalanceAdjustmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    /**
     * @var AgentWithdrawal
     */
    private $withdrawal;

    /**
     * Create a new job instance.
     *
     * @param AgentWithdrawal $withdrawal
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
        $agentAccount = AgentBalance::where('user_id', $this->withdrawal->user_id)->first();
        $agentAccount->balance -= $this->withdrawal->amount;
        $agentAccount->save();
        $commission = AgentCommission::firstOrNew(['user_id' => $this->withdrawal->user_id, 'type' => 'debit', 'purpose' => 'withdrawal', 'commission_date' => $this->withdrawal->created_at->format('Y-m-d')]);
        $commission->total_sale += $this->withdrawal->balance;
        $commission->amount += $this->withdrawal->amount;
        $commission->save();
    }
}
