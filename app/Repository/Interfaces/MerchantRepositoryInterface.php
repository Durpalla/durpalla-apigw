<?php
namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface MerchantRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);
}
