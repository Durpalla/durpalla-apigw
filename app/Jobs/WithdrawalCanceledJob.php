<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AgentWithdrawal;

class WithdrawalCanceledJob implements ShouldQueue
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
        //
    }
}
