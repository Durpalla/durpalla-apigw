<?php


namespace App\Repository\Interfaces;



use Illuminate\Support\Collection;

interface RouteRepositoryInterface
{
    public function all() : Collection;
}
