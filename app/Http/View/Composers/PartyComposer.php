<?php


namespace App\Http\View\Composers;


use App\Services\Parties;
use Illuminate\View\View;

class PartyComposer
{
    protected $party;

    /**
     * Create a new party composer.
     *
     * @param Parties $party
     */
    public function __construct(Parties $party)
    {
        $this->party = $party;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('party_dropdowns', $this->party->getDropDown());
    }
}
