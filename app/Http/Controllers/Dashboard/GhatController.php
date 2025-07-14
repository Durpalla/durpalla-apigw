<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use App\Models\Ghat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Image;
use App\Http\Requests\GhatCreateRequest;
use App\Http\Requests\GhatUpdateRequest;
use App\Services\GhatService;

class GhatController extends Controller
{
    private $ghatService;
    public function __construct(GhatService $ghatService)
    {
        $this->ghatService = $ghatService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $query = Ghat::query();

            if(isset($_GET['status'])) {
                if($_GET['status'] == 9) {
                    $query->onlyTrashed();
                }
            }

            if($request->filled('service_type')) {
                $query->where('service_type', $_GET['service_type']);
            }

            $count = $query->count();
            $query->offset($start);
            if ($limit < 0) {
                $limit = $count;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $ghats = $query->get()->toArray();
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $ghats
            ];

            return response()->json($data);
        }

        return view('admin.ghat.index')->withTitle('Manage ghats');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.ghat.create')->withTitle('Add new ghat');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(GhatCreateRequest $request)
    {
        try {
            DB::transaction(function() use ($request) {
                $this->ghatService->create($request->validated());
            }, 2);
            return redirect()->route('dashboard.ghat.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->back()->withInput($request->all());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $ghat = Ghat::findOrFail($id);
        return view('admin.ghat.edit', compact('ghat'))->withTitle('Update ghat');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(GhatUpdateRequest $request, $id)
    {
        try {
            DB::transaction(function() use ($request, $id) {
                $this->ghatService->update($request->validated(), $id);
            }, 2);
            return redirect()->route('dashboard.ghat.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->back()->withInput($request->all());
    }

    public function suggest(Request $request)
    {
        $query = Ghat::select('name', 'id');

        if (isset($_GET['term'])) {
            $term = $_GET['term'];
            $query->where('name', 'LIKE', '%' . $term . '%');
        }

        if(isset($_GET['service_type'])) {
            $query->where('service_type', $_GET['service_type']);
        }

        $query = $query->paginate(15);

        $results = [];

        if ($query) {
            foreach ($query as $q) {
                $row['id'] = $q->id;
                $row['name'] = $q->name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function action(Request $request)
    {
        if (isset($request->action) && in_array($request->action, ['delete', 'restore'])) {
            return call_user_func(array($this, $request->action), $request);
        } else {
            return response()->json(['status' => false, 'content' => 'Your action not valid', 'label' => 'error']);
        }
    }

    public function delete($request): JsonResponse
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot delete stoppage'];
        try {
            DB::transaction(function () use (&$data, $request) {
                $stoppage = Ghat::find($request->id);
                if ($stoppage->delete()) {

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Stoppage has been successfully deleted';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = $e->getMessage();
        }

        return response()->json($data);
    }

    public function restore($request): JsonResponse
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot restore stoppage'];
        try {
            DB::transaction(function () use (&$data, $request) {
                $stoppage = Ghat::withTrashed()->find($request->id);
                if ($stoppage->restore()) {

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Stoppage has been successfully restored';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = $e->getMessage();
        }

        return response()->json($data);
    }
}
