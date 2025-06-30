<?php


namespace App\Http\View\Composers;


use App\Services\GhatService;
use Illuminate\View\View;

class GhatComposer
{
    /**
     * The user repository implementation.
     *
     * @var GhatService
     */
    protected $ghat;

    /**
     * Create a new profile composer.
     *
     * @param  GhatService  $ghatService
     * @return void
     */
    public function __construct(GhatService $ghatService)
    {
        $this->ghat = $ghatService;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('ghat_dropdowns', $this->ghat->getDropDown());
    }
}
