<?php


namespace App\Http\View\Composers;


use App\Services\GatewayService;
use Illuminate\View\View;

class GatewayComposer
{
    protected $gateway;

    /**
     * Create a new gateway composer.
     *
     * @param  GatewayService $gateway
     * @return void
     */
    public function __construct(GatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('gateway_list', $this->gateway->getActive());
    }
}
