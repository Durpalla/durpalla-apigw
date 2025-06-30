<?php

namespace Modules\Vehicle\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Exports\DailyTripReportExport;
use App\Services\DailyTripBookingItemReport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleController extends Controller
{
    private $dailyTripBookingItem;
    public function __construct(DailyTripBookingItemReport $dailyTripBookingItemReport)
    {
        $this->dailyTripBookingItem = $dailyTripBookingItemReport;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('vehicle::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('vehicle::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('vehicle::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('vehicle::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    public function downloadReport($id): BinaryFileResponse
    {
        if ($id) {
            $results = $this->dailyTripBookingItem->getTripReport($id, 'all');
            return Excel::download(new DailyTripReportExport($results, ''), 'Trip-Report-' . $id . '.xlsx');
        }
    }
}
