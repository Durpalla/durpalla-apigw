<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use App\Http\Requests\GatewayCreateRequest;
use App\Http\Requests\GatewayUpdateRequest;
use App\Models\Gateway;
use App\Repository\Interfaces\GatewayRepositoryInterface;

class GatewayController extends Controller
{
    private $repository;
    public function __construct(GatewayRepositoryInterface $gatewayRepository)
    {
        $this->repository = $gatewayRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        $gateways = $this->repository->all();
        return view('admin.gateway.index', compact('gateways'))->withTitle('Gateway managers');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.gateway.create')->withTitle('Add new gateway');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\GatewayCreateRequest $request
     * @return RedirectResponse
     */
    public function store(GatewayCreateRequest $request): RedirectResponse
    {
        try {
            $this->repository->create($request->validated());
        } catch (\Exception $exception) {
            session()->flash('error', $exception->getMessage());
            return redirect()->back();
        }

        return redirect()->route('gateway.index');
    }

    /**
     * Display the specified resource.
     *
     * @param Gateway $gateway
     * @return Response
     */
    public function show(Gateway $gateway): Response
    {
        return view('admin.gateway.show', compact('gateway'))->withTitle('View gateway');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Gateway $gateway
     * @return View
     */
    public function edit(Gateway $gateway): View
    {
        return view('admin.gateway.edit', compact('gateway'))->withTitle('Update gateway');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\GatewayUpdateRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(GatewayUpdateRequest $request, $id): RedirectResponse
    {
        try {
            $this->repository->update($request->validated(), $id);
        } catch (\Exception $exception) {
            session()->flash('error', $exception->getMessage());
            return redirect()->back();
        }

        return redirect()->route('gateway.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(Gateway $gateway): RedirectResponse
    {
        $gateway->delete();
        return redirect()->back();
    }
}
