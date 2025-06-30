<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface CancellationRepositoryInterface
{
    public function all() : Collection;
}
