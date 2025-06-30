<?php


namespace App\Services;


use Illuminate\Support\Facades\Cache;
use App\Models\AgentBalance;

class BalanceService
{
    private $userID;
    public function getMyBalance($userID)
    {
        $this->userID = $userID;

        return Cache::remember('my_balance_' . $this->userID, 300, function() {
            $item = AgentBalance::where('user_id', $this->userID)->first();
            return ($item) ? $item->balance : 0;
        });
    }
}
