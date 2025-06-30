<?php


namespace App\Repository\Interfaces;


interface AgentPaymentMethodRepositoryInterface
{
    public function get(int $userID);
}
