<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface ServiceRepositoryInterface
{
    public function all(): Collection;

    public function create(array $data);

    public function update(array $data, $id);
}
