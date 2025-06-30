<?php

namespace Modules\BroadCast\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\BroadCast\BroadcastService;
use Modules\BroadCast\Entities\BroadCast;
use Modules\BroadCast\Http\Requests\BroadcastCreateRequest;
use Modules\BroadCast\Http\Requests\BroadcastUpdateRequest;
use Modules\BroadCast\Repository\BroadcastRepositoryInterface;

class BroadCastController extends Controller
{
    private $broadcast;
    private $broadcastService;

    public function __construct(
        BroadcastRepositoryInterface $broadcastRepository,
        BroadcastService $broadcastService
    )
    {
        $this->broadcast = $broadcastRepository;
        $this->broadcastService = $broadcastService;
    }

    /**
     * Display a listing of the resource.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        if(request()->wantsJson()) {
            return $this->broadcastService->getDataTable();
        }
        return view('broadcast::index')->withTitle('Broadcast Messages');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create(): Renderable
    {
        return view('broadcast::create')->withTitle('Add new message');
    }

    /**
     * Store a newly created resource in storage.
     * @param BroadcastCreateRequest $request
     * @return RedirectResponse
     */
    public function store(BroadcastCreateRequest $request): RedirectResponse
    {
        try {
            $this->broadcastService->create($request->except(['attachment', 'customers']));
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
            return redirect()->back();
        }

        return redirect()->route('broadcast.index');
    }

    /**
     * Show the specified resource.
     * @param BroadCast $broadcast
     * @return Renderable
     */
    public function show(BroadCast $broadcast)
    {
        return view('broadcast::show', compact('broadcast'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param BroadCast $broadcast
     * @return Renderable
     */
    public function edit(BroadCast $broadcast): Renderable
    {
        return view('broadcast::edit', compact('broadcast'))->withTitle('Update message');
    }

    /**
     * Update the specified resource in storage.
     * @param BroadcastUpdateRequest $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(BroadcastUpdateRequest $request, $id): RedirectResponse
    {
        try {
            $data = $request->except(['attachment', 'customers']);
            $this->broadcastService->update($data, $id);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
            return redirect()->back();
        }

        return redirect()->route('broadcast.index');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->broadcast->delete($id);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        return redirect()->back();
    }
}
