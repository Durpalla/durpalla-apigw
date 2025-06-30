<?php


namespace App\Repository\Interfaces;


use Illuminate\Support\Collection;

interface OptionRepositoryInterface
{
    public function all() : Collection;
}
