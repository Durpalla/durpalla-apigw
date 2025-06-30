<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalUpdateRequest;
use App\Models\AgentWithdrawal;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;
use App\Services\WithdrawalService;

class AgentWithdrawalController extends Controller
{
    private $withdrawals;
    private $withdrawal;

    public function __construct(
        WithdrawalService $withdrawals,
        WithdrawalRepositoryInterface $withdrawal
    )
    {
        $this->withdrawals = $withdrawals;
        $this->withdrawal = $withdrawal;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request()->wantsJson()) {
            return $this->withdrawals->getDataTable(request()->all());
        }

        return view('admin.agent.withdrawal.index')->withTitle('Withdrawals');
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
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param AgentWithdrawal $withdrawal
     * @return \Illuminate\Http\Response
     */
    public function show(AgentWithdrawal $withdrawal)
    {
        return view('admin.agent.withdrawal.show', compact('withdrawal'))->withTitle('Show withdrawal');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param WithdrawalUpdateRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(WithdrawalUpdateRequest $request, $id): \Illuminate\Http\RedirectResponse
    {
        try {
            $withdrawal = $this->withdrawal->update($request->all() + [
                'officer_id' => auth()->user()->id
                ], $id);
            session()->flash('success', 'Withdrawal has been successfully updated');
        } catch (\Exception $exception) {
            session()->flash('error', $exception->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
