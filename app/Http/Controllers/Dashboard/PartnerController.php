<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\PartnerCreateRequest;
use App\Http\Requests\PartnerUpdateRequest;
use App\Models\Partner;
use App\Services\PartnerService;

class PartnerController extends Controller
{
    private $partnerService;
    public function __construct(PartnerService $partnerService)
    {
        $this->partnerService = $partnerService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if(request()->wantsJson()) {
            return $this->partnerService->getIndex(request()->all());
        }
        return view('admin.partner.index')->withTitle('Partners');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.partner.create')->withTitle('Add new partner');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param PartnerCreateRequest $request
     * @return RedirectResponse
     */
    public function store(PartnerCreateRequest $request): RedirectResponse
    {
        try {
            $this->partnerService->create($request->all());
        } catch (\Exception $exception) {
            session()->flash($exception->getMessage());
            return redirect()->back()->withInput($request->all());
        }

        return redirect()->route('partner.index');
    }

    /**
     * Display the specified resource.
     *
     * @param Partner $partner
     * @return Response
     */
    public function show(Partner $partner)
    {
        return view('admin.partner.show', compact('partner'))->withTitle('Partner: ' . $partner->name);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Partner $partner
     * @return Response
     */
    public function edit(Partner $partner)
    {
        return view('admin.partner.edit', compact('partner'))->withTitle('Update partner');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PartnerUpdateRequest $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(PartnerUpdateRequest $request, int $id): RedirectResponse
    {
        try {
            $this->partnerService->update($request->validated(), $id);
        } catch (\Exception $exception) {
            session()->flash($exception->getMessage());
            return redirect()->back()->withInput($request->all());
        }

        return redirect()->route('partner.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    public function suggest(): JsonResponse
    {
        $results = $this->partnerService->suggest(request()->get('term'));

        return response()->json(['results' => $results], 200);
    }

    public function suggestVehicles(): JsonResponse
    {
        $results = $this->partnerService->suggestVehicle(request()->get('term'));

        return response()->json(['results' => $results], 200);
    }

    public function bookings($id): JsonResponse
    {
        $bookings = $this->partnerService->getBookings($id, request()->all());
        return response()->json($bookings);
    }
}
