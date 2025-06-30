<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerVehicleAssignRequest;
use App\Models\Partner;

class PartnerVehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function store(PartnerVehicleAssignRequest $request): RedirectResponse
    {
        try {
            $partner = Partner::find($request->partner_id);
            $partner->vehicles()->attach($request->vehicle_id);
            session()->flash('success', 'Successfully assign vehicle to partner account');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy($id): RedirectResponse
    {
        try {
            $partner = Partner::find(auth()->user()->id);
            $partner->partnerVehicles()->detach($id);
            session()->flash('success', 'Successfully detach vehicles from partner account.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        return redirect()->back();
    }
}
