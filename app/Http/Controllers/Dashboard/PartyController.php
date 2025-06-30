<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartyCreateRequest;
use App\Http\Requests\PartyUpdateRequest;
use App\Models\Party;
use App\Services\Parties;

class PartyController extends Controller
{
    private $parties;
    public function __construct(Parties $parties)
    {
        $this->parties = $parties;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $parties = Party::paginate(15);
        return view('admin.party.index', compact('parties'))->withTitle('Manage parties');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.party.create')->withTitle('Add new party');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(PartyCreateRequest $request)
    {
        try {
            DB::transaction(function() use($request) {
                $request->merge([
                    'officer_id' => auth()->user()->id,
                    'party' => $request->slug
                ]);
                $this->parties->create($request->all());
            }, 2);
        } catch (\Exception $exception) {
            session()->flash('error', $exception->getMessage() . ' Line-' . $exception->getLine());
            return redirect()->back()->withInput($request->all());
        }
        return redirect()->route('parties.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Party $party)
    {
        return view('admin.party.show', compact('party'))->withTitle('View party details');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Party $party)
    {
        return view('admin.party.edit', compact('party'))->withTitle('Update party');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  PartyUpdateRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PartyUpdateRequest $request, $id)
    {
        try {
            $this->parties->update($request->all(), $id);
        } catch (\Exception $exception) {
            session()->flash('error', $exception->getMessage() . ' File -' . $exception->getFile() . ' Line-' . $exception->getLine());
            return redirect()->back()->withInput($request->all());
        }
        return redirect()->route('parties.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(Party $party)
    {
        try {
            $party->delete();
        } catch (\Exception $exception){
            session()->flash('error', $exception->getMessage());
        }

        return redirect()->back();
    }
}
