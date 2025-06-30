<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Image;
use Illuminate\Support\Facades\Validator;

class SponsorController extends Controller
{
    protected $success = 200;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index( Request $request )
    {
        if( $request->ajax() == true ) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $query = Sponsor::with(['user']);

            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = ( int ) $_GET['status'];
                $query->where('status', $status);
            }
            $count = $query->count();
            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy('created_at', 'desc');
            $sponsors = $query->get();
            $sponsors = $sponsors->map(function($item) {
                $item->attachment = asset($item->attachment);
                return $item;
            });
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $sponsors->toArray()
            ];
            return response()->json( $data );
        }
        return view('admin.sponsor.index')->withTitle('Sponshorship');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.sponsor.create')->withTitle('Add new sponsor');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot create sponsor'];
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|unique:sponsors,title',
            'attachment' => 'bail|required|mimes:png,jpg,jpeg',
            'expire_date'=>'bail|nullable'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        } else {
            try{
                DB::transaction(function() use($request, &$data) {
                    if($request->file('attachment')) {
                        $image = $request->file('attachment');
                        $filename = time().'.'.$image->getClientOriginalExtension();
                        $destinationPath = public_path('/sponsors');
                        $img = Image::make($image->getRealPath());
                        $img->resize(460, 340, function ($constraint) {
                            $constraint->aspectRatio();
                        })->save($destinationPath.'/'.$filename);
                        $attachment = 'sponsors/' . $filename;
                    }
                    Sponsor::create([
                       'title' => $request->title,
                       'attachment' => $attachment,
                       'expire_at' => ($request->expire_date) ? date('Y-m-d 23:59:59', $request->expire_date) : date('Y-m-d 23:59:59')
                    ]);
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Your sponsorship has been successfully created';
                }, 2);
            } catch (\Exception $e) {
                $data['content'] = $e->getMessage();
            }
        }
        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            return redirect()->route('dashboard.sponsor.index')->with([
                'message' => $data
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $sponsor = Sponsor::findOrFail($id);
        return view('admin.sponsor.edit', compact('sponsor'))->withTitle('Edit sponsorship');
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot create launch'];
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|unique:sponsors,title,' . $id,
            'attachment' => 'bail|required|mimes:png,jpg,jpeg',
            'expire_date'=>'bail|nullable'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        } else {
            try{
                DB::transaction(function() use($request, &$data, $id) {
                    $sponsor = Sponsor::find($id);
                    $sponsor->title = $request->title;
                    $sponsor->expire_at = ($request->expire_date) ? date('Y-m-d 23:59:59', $request->expire_date) : $sponsor->expire_at;
                    if($request->file('attachment')) {
                        $image = $request->file('attachment');
                        $filename = time().'.'.$image->getClientOriginalExtension();
                        $destinationPath = public_path('/sponsors');
                        $img = Image::make($image->getRealPath());
                        $img->resize(460, 340, function ($constraint) {
                            $constraint->aspectRatio();
                        })->save($destinationPath.'/'.$filename);
                        $sponsor->attachment = 'sponsors/' . $filename;
                    }
                    $sponsor->save();
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Your sponsorship has been successfully updated';
                }, 2);
            } catch (\Exception $e) {
                $data['content'] = $e->getMessage();
            }
        }
        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true) {
                return redirect()->route('dashboard.sponsor.index')->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator->errors())->withInput($request->all());
            }
        }
    }

    public function action( Request $request )
    {
        if( isset( $request->type ) && in_array($request->type, ['enable', 'disable']) ) {
            return call_user_func(array($this, $request->type), $request);
        } else {
            return response()->json(['status' => false, 'content' => 'Your action type not valid', 'label' => 'error']);
        }
    }

    private function enable($request)
    {
        $sponsor = Sponsor::find($request->id);
        $sponsor->status = 1;
        if( $sponsor->save() ) {
            return response()->json(['status' => true, 'content' => 'Coupon has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon has been enabled.', 'label' => 'error']);
        }
    }

    private function disable($request)
    {
        $sponsor = Sponsor::find($request->id);
        $sponsor->status = 2;
        if( $sponsor->save() ) {
            return response()->json(['status' => true, 'content' => 'Coupon has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon has been enabled.', 'label' => 'error']);
        }
    }
}
