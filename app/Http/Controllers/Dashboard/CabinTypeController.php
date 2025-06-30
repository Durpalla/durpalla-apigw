<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\CabinType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CabinTypeCreateRequest;
use App\Http\Requests\CabinTypeUpdateRequest;
use App\Services\CabinTypeService;

class CabinTypeController extends Controller
{
    protected $success = 200;
    private $cabinType;
    public function __construct(CabinTypeService $cabinTypeService)
    {
        $this->cabinType = $cabinTypeService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if( $request->ajax() === True ) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = CabinType::where('service_type', $_GET['service_type']);

            //filter by keyword
            // if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
            //     $keyword = $_GET['keyword'];
            //     $query->orWhere('merchantID', 'LIKE', "%$keyword%");
            //     $query->orWhere('primary_contact', 'LIKE', "%$keyword%");
            //     $query->orWhere('secondary_contact', 'LIKE', "%$keyword%");
            //     $query->orWhereHas('user', function ($q) use ($keyword) {
            //         $q->where('first_name', 'LIKE',"%$keyword%");
            //         $q->orWhere('last_name', 'LIKE',"%$keyword%");
            //         $q->orWhere('username', 'LIKE',"%$keyword%");
            //         $q->orWhere('email', 'LIKE',"%$keyword%");
            //         $q->orWhere('mobile', 'LIKE',"%$keyword%");
            //     });
            // }

            $total = $query->count();

            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
             $query->orderBy($column, $order);
            $types = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $types->toArray()
            ];

            return response()->json( $data );
        }
        return view('admin/cabintype/index')->withTitle('Cabin-Seat types');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin/cabintype/create')->withTitle('Add new cabin-seat type');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CabinTypeCreateRequest $request)
    {
        try {
            DB::transaction(function() use ($request) {
                $this->cabinType->create($request->validated());
            }, 2);
            return redirect()->route('dashboard.cabintype.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $cabin_type = CabinType::findOrFail($id);
        return view('admin/cabintype/show', compact('cabin_type'))->withTitle('View cabin type');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $cabin_type = CabinType::findOrFail($id);
        return view('admin/cabintype/edit', compact('cabin_type'))->withTitle('Edit ' . ucfirst($cabin_type->type) . ' type');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(CabinTypeUpdateRequest $request, $id)
    {
        try {
            DB::transaction(function() use ($request, $id) {
                $this->cabinType->update($request->validated(), $id);
            }, 2);
            return redirect()->route('dashboard.cabintype.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $cabin_type = CabinType::findOrFail($id);

        if ($cabin_type->destroy()) {
            return redirect()->route('dashboard.cabintype.index')->with([
                'message' => [
                    'label' => 'success',
                    'content' => 'Cabin type has been successfully deleted'
                ]
            ]);
        }
    }
}
