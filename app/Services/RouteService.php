<?php


namespace App\Services;


use Illuminate\Support\Facades\Cache;
use App\Repository\Interfaces\RouteRepositoryInterface;

class RouteService
{
    private $route;
    public function __construct( RouteRepositoryInterface $routeRepository)
    {
        $this->route = $routeRepository;
    }

    public function getDropDown()
    {
        return Cache::rememberForever('route_dropdowns', function() {
            return $this->route->all()->pluck('route_name', 'id');
        });
    }
}
