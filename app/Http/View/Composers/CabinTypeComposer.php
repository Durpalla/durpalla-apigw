<?php


namespace App\Http\View\Composers;


use App\Services\CabinTypeService;
use Illuminate\View\View;

class CabinTypeComposer
{
    /**
     * The user repository implementation.
     *
     * @var
     */
    protected $cabinType;

    /**
     * Create a new profile composer.
     *
     * @param  CabinTypeService  $cabinType
     * @return void
     */
    public function __construct(CabinTypeService $cabinType)
    {
        $this->cabinType = $cabinType;
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
            'cabin_type_dropdowns' => $this->cabinType->getCabinTypeDropDown(),
            'seat_type_dropdowns' => $this->cabinType->getSeatTypeDropDown()
        ]);
    }
}
