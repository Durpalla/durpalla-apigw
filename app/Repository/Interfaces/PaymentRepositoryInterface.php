<?php
namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface PaymentRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);
}
