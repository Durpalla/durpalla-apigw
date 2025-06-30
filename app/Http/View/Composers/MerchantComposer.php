<?php


namespace App\Http\View\Composers;


use App\Services\MerchantService;
use Illuminate\View\View;

class MerchantComposer
{
    /**
     * The user repository implementation.
     *
     * @var MerchantService
     */
    protected $merchant;

    /**
     * Create a new profile composer.
     *
     * @param  MerchantService  $merchant
     * @return void
     */
    public function __construct(MerchantService $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('merchant_dropdowns', $this->merchant->getDropDown());
    }
}
