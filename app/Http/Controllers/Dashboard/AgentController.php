<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\AgentCreateRequest;
use App\Http\Requests\AgentUpdateRequest;
use App\Models\Agent;
use App\Services\AgentService;

class AgentController extends Controller
{
    private $agentService;
    public function __construct(AgentService $agentService)
    {
        $this->agentService = $agentService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if(request()->wantsJson()) {
            return $this->agentService->getIndex(request()->all());
        }
        return view('admin.agent.index')->withTitle('Agents');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('admin.agent.create')->withTitle('Add new agent');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AgentCreateRequest $request
     * @return RedirectResponse
     */
    public function store(AgentCreateRequest $request): RedirectResponse
    {
        try {
            if($request->hasFile('nid_attachment')) {
                /*logo upload*/
                $imageName = 'NID_' . time() . '.' . $request->nid_attachment->extension();
                $request->nid_attachment->move(public_path('images'), $imageName);
                $request->merge([
                    'nid_photo' => $imageName
                ]);
            }
            if($request->hasFile('trade_attachment')) {
                $imageName = 'Trade_license_' . time() . '.' . $request->trade_attachment->extension();
                $request->trade_attachment->move(public_path('images'), $imageName);
                $request->merge([
                    'trade_license_photo' => $imageName
                ]);
            }
            $this->agentService->create($request->all());
        } catch (\Exception $exception) {
            session()->flash($exception->getMessage());
            return redirect()->back()->withInput($request->all());
        }

        return redirect()->route('agent.index');
    }

    /**
     * Display the specified resource.
     *
     * @param Agent $agent
     * @return Response
     */
    public function show(Agent $agent)
    {
        return view('admin.agent.show', compact('agent'))->withTitle('Agent: ' . $agent->name);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Agent $agent
     * @return Response
     */
    public function edit(Agent $agent)
    {
        return view('admin.agent.edit', compact('agent'))->withTitle('Update agent');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AgentUpdateRequest $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(AgentUpdateRequest $request, int $id): RedirectResponse
    {
        try {
            $this->agentService->update($request->validated(), $id);
        } catch (\Exception $exception) {
            session()->flash($exception->getMessage());
            return redirect()->back()->withInput($request->all());
        }

        return redirect()->route('agent.index');
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
        $results = $this->agentService->suggest(request()->get('term'));

        return response()->json(['results' => $results], 200);
    }

    public function bookings($id): JsonResponse
    {
        $bookings = $this->agentService->getBookings($id, request()->all());
        return response()->json($bookings);
    }
}
