<?php
namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface BookingRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);

    public function find($booking_id);

    public function update(array $data, $id);
}
