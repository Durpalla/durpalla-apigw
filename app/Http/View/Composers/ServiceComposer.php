<?php


namespace App\Http\View\Composers;


use App\Services\Services;
use Illuminate\View\View;

class ServiceComposer
{
    /**
     * The user repository implementation.
     *
     * @var $services
     */
    protected $services;

    /**
     * Create a new profile composer.
     *
     * @param  Services  $services
     * @return void
     */
    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with([
            'service_dropdowns' => $this->services->getDropDown(),
            'service_list' => $this->services->getServices()
        ]);
    }
}
