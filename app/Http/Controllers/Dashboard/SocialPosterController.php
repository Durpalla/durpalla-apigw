<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Coupon;
use App\Http\Controllers\Controller;
use App\Models\VehicleSchedule;
use App\Models\SocialPoster;
use Image;

class SocialPosterController extends Controller
{
    protected $success = 200;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $query = SocialPoster::with(['user', 'VehicleSchedule']);

            if (isset($_GET['status'])) {
                $status = (int)$_GET['status'];
                if($status == 1) {
                    $query->whereHas('VehicleSchedule', function($q) use($status) {
                        $q->where('leaving_at', '>=', date('Y-m-d H:i:s'));
                    });
                } else {
                    $query->whereHas('VehicleSchedule', function($q) use($status) {
                        $q->where('leaving_at', '<=', date('Y-m-d H:i:s'));
                    });
                }
            }

            $count = $query->count();
            $query->offset($start);
            if ($limit < 0) {
                $limit = $count;
            }
            $query->limit($limit);
            $posters = $query->get();
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $posters->map(function($item, $k) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'photo' => asset($item->poster),
                        'launch_name' => $item->launch_name,
                        'route_name' => $item->route_name,
                        'created_by' => ($item->user) ? $item->user['name'] : '',
                        'counter' => $item->share_count,
                        'validity' => date('d/m/Y h:i a', strtotime($item->VehicleSchedule->leaving_at)),
                    ];
                })
            ];

            return response()->json($data);
        }

        return view('admin.social.index')->withTitle('Social media posters');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.social.create')->withTitle('Add new poster');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot create banner'];
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string',
            'launch_schedule_id' => 'bail|required|numeric|exists:vehicle_schedules,id',
            'poster' => 'bail|required|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=460,min_height=340'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();
        try {
            //upload poster/banner
            $poster = null;
            if ($request->file('poster')) {
                $image = $request->file('poster');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = public_path('uploads/posters/');
                $img = Image::make($image->getRealPath());
                $img->resize(460, 340, function ($constraint) {
                    $constraint->aspectRatio();
                })->save($destinationPath . '/' . $filename);

                $poster = '/uploads/posters/' . $filename;
            }

            $trip = VehicleSchedule::findOrFail($request->launch_schedule_id);
            $route = explode('-', $trip->route->route_name);
            $route_name = ($trip->schedule_type == 'reverse') ? trim($route[1] . ' - ' . trim($route[0])) : $route[0] . '-' . $route[1];
            SocialPoster::create([
                'name' => $request->name,
                'description' => $request->description,
                'launch_schedule_id' => $trip->id,
                'merchant_id' => $request->merchant_id,
                'vehicle_id' => $request->vehicle_id,
                'launch_name' => $trip->launch->name,
                'route_name' => $route_name,
                'user_id' => Auth::user()->id,
                'poster' => $poster
            ]);

            DB::commit();
            $data['content'] = 'Banner successfully created';
            $data['label'] = 'success';
            $data['status'] = true;
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->route('dashboard.social.index')->with([
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $social = SocialPoster::findOrFail($id);
        return view('admin.social.view', compact('social'))->with(['mappings'])->withTitle('View poster');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $poster = SocialPoster::findOrFail($id);
        return view('admin.social.edit', compact('poster'))->withTitle('Update poster');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot update poster'];
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string',
            'launch_schedule_id' => 'bail|required|numeric|exists:vehicle_schedules,id',
            'poster' => 'bail|nullable|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=460,min_height=340'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $trip = VehicleSchedule::findOrFail($request->launch_schedule_id);
            $route = explode('-', $trip->route->route_name);
            $route_name = ($trip->schedule_type == 'reverse') ? trim($route[1] . ' - ' . trim($route[0])) : $route[0] . '-' . $route[1];
            $social = SocialPoster::findOrFail($id);
            $social->update([
                'name' => $request->name,
                'description' => $request->description,
                'launch_schedule_id' => $trip->id,
                'merchant_id' => $request->merchant_id,
                'vehicle_id' => $request->vehicle_id,
                'launch_name' => $trip->launch->name,
                'route_name' => $route_name
            ]);

            //upload poster/banner
            if ($request->file('poster')) {
                $image = $request->file('poster');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = public_path('uploads/posters/');
                $img = Image::make($image->getRealPath());
                $img->resize(460, 340, function ($constraint) {
                    $constraint->aspectRatio();
                })->save($destinationPath . '/' . $filename);

                $social->poster = '/uploads/posters/' . $filename;
            }

            if ($social->save()) {
                DB::commit();
                $data['content'] = 'Poster successfully updated';
                $data['label'] = 'success';
                $data['status'] = true;
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }
        if ($validator->fails()) {
            $data['msg'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->route('dashboard.social.index')->with([
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot delete coupon'];
        $coupon = Coupon::findOrFail($id);

        DB::beginTransaction();
        try {
            $coupon->delete();
            DB::commit();
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Coupon has been successfully deleted';
        } catch (\Exception $e) {
            DB::rollback();
        }

        return response()->json($data, $this->success);
    }

    public function action(Request $request)
    {
        if (isset($request->type) && in_array($request->type, ['enable', 'disable'])) {
            return call_user_func(array($this, $request->type . 'Bulk'), $request);
        } else {
            return response()->json(['status' => false, 'content' => 'Your action type not valid', 'label' => 'error']);
        }
    }

    public function enableBulk($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $coupons = Coupon::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($coupons, &$data, $request) {
                if ($coupons) {
                    foreach ($coupons as $coupon) {
                        $coupon->update(['status' => 1]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Coupons are successfully enabled';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = 'Error occured';
        }

        if ($request->ajax() === True) {
            return response()->json($data);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function disableBulk($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $coupons = Coupon::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($coupons, &$data, $request) {
                if ($coupons) {
                    foreach ($coupons as $coupon) {
                        $coupon->update(['status' => 2]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Coupons are successfully disabled';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = 'Error occured';
        }

        if ($request->ajax() === True) {
            return response()->json($data);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function enableCoupon($request)
    {
        $coupon = Coupon::find($request->id);
        if ($coupon->update(['status' => 1])) {
            return response()->json(['status' => true, 'content' => 'Coupon has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon cannot be enabled.', 'label' => 'error']);
        }
    }

    private function disableCoupon($request)
    {
        $coupon = Coupon::find($request->id);
        if ($coupon->update(['status' => 2])) {
            return response()->json(['status' => true, 'content' => 'Coupon has been disabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon cannot be disabled.', 'label' => 'error']);
        }
    }
}
