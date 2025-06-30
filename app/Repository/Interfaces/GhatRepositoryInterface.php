<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface GhatRepositoryInterface
{
    public function all() : Collection;

    public function create(array $data);

    public function update(array $data, int $id);

    public function allActive();
}
