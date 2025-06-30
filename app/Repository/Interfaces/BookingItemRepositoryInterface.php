<?php
namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface BookingItemRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);
}
