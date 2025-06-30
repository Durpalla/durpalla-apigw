<?php


namespace App\Http\View\Composers;


use App\Services\RouteService;
use Illuminate\View\View;

class RouteComposer
{
    /**
     * The user repository implementation.
     *
     * @var RouteService
     */
    protected $route;

    /**
     * Create a new profile composer.
     *
     * @param  RouteService  $routeService
     * @return void
     */
    public function __construct(RouteService $routeService)
    {
        $this->route = $routeService;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('route_dropdowns', $this->route->getDropDown());
    }
}
