<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\DeckFare;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeckFareController extends Controller
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
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Deck fare cannot be saved.'];
        $validator = Validator::make($request->all(), [
            'route_id'=>'bail|required|string|max:191|exists:vehicle_routes,id',
            'merchant_id' => 'bail|required|integer|exists:users,id',
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'departure_from' => 'bail|required|integer|exists:route_properties,id',
            'departure_to' => 'bail|required|integer|exists:route_properties,id',
            'deck_fare'=>'bail|required',
            'type' => 'bail|nullable'
        ]);

        //validation fails
        if ( $validator->fails() ) {
//            Log::debug($validator->errors() );
            $data['content'] = $validator->errors()->first();
            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $deckFare = DeckFare::firstOrNew([
                'route_id' => $request->route_id,
                'merchant_id' => $request->merchant_id,
                'vehicle_id' => $request->vehicle_id,
                'departure_from' => $request->departure_from,
                'departure_to' => $request->departure_to
            ]);

            $deckFare->route_id = $request->route_id;
            $deckFare->merchant_id = $request->merchant_id;
            $deckFare->vehicle_id = $request->vehicle_id;
            $deckFare->departure_from = $request->departure_from;
            $deckFare->departure_to = $request->departure_to;
            $deckFare->user_id = Auth::user()->id;
            $deckFare->fare = abs( $request->deck_fare );
            $deckFare->reverse_fare = abs( $request->reverse_fare );
            // $deckFare->type = ( $request->type && $request->type == 'reverse' ) ? 'reverse' : 'straight';

            if( $deckFare->save() ) {
                $data['deck'] = $deckFare;
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = "You have successfully saved deck fare.";
            }
            DB::commit();
        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.vehicle.show', ['id' => $request->vehicle_id, 'tab' => 'deck'])->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show( $id )
    {
        $fare = DeckFare::findOrFail( $id );
        return view('admin.fare.show', compact('fare'))->withTitle('Edit fare');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
    */
    public function edit($id)
    {
        $fare = DeckFare::findOrFail( $id );
        return view('admin.fare.edit', compact('fare'))->withTitle('Edit fare');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Deck fare cannot be updated.'];
        $validator = Validator::make($request->all(), [
            'route_id'=>'bail|required|string|max:191|exists:vehicle_routes,id',
            'merchant_id' => 'bail|required|integer|exists:users,id',
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'departure_from' => 'bail|required|integer|exists:route_properties,id',
            'departure_to' => 'bail|required|integer|exists:route_properties,id',
            'deck_fare'=>'bail|required',
            'reverse_fare' => 'bail|required',
            'type' => 'bail|nullable'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $deckFare = DeckFare::findOrFail( $id );
            $deckFare->route_id = $request->route_id;
            $deckFare->merchant_id = $request->merchant_id;
            $deckFare->vehicle_id = $request->vehicle_id;
            $deckFare->departure_from = $request->departure_from;
            $deckFare->departure_to = $request->departure_to;
            $deckFare->user_id = Auth::user()->id;
            $deckFare->fare = abs( $request->deck_fare );
            $deckFare->reverse_fare = abs( $request->reverse_fare );
            // $deckFare->type = ( $request->type && $request->type == 'reverse' ) ? 'reverse' : 'straight';

            if( $deckFare->save() ) {
                $data['deck'] = $deckFare;
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = "You have successfully updated deck fare.";
            }
            DB::commit();
        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.vehicle.show', ['id' => $request->vehicle_id, 'tab' => 'deck'])->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy( Request $request, $id )
    {
        //
    }
}
