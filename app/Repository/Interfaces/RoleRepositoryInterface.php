<?php
namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function all() : Collection;
    public function create(array $data);
}
