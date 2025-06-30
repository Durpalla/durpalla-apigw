<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface ScheduleCabinMappingRepositoryInterface
{
    public function all(): Collection;

    public function create(array $data);

    public function update(array $data, $id);
}
