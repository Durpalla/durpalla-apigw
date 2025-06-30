<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface PartnerRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);
    public function update(array $data, $id);
    public function delete(int $id);

    public function getCommissions($agent_id, array $data);
}
