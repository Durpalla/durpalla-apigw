<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Image;

class BannerController extends Controller
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
            $query = Coupon::with(['user'])->where('type', 'banner');

            if (isset($_GET['status'])) {
                $status = (int)$_GET['status'];
                $query->where('status', $status);
            }

            $count = $query->count();
            $query->offset($start);
            if ($limit < 0) {
                $limit = $count;
            }
            $query->limit($limit);
            $coupons = $query->get()->toArray();
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $coupons
            ];

            return response()->json($data);
        }

        return view('admin.banner.index')->withTitle('Manage banners');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.banner.create')->withTitle('Add new banner');
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
            'name' => 'bail|required|string|max:191,title',
            'description' => 'bail|nullable|string,content',
            'offer_start' => 'bail|required',
            'offer_end' => 'bail|required',
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
            //upload poster/banner
            $poster = null;
            if ($request->file('poster')) {
                $image = $request->file('poster');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = public_path('uploads/banner/');
                $img = Image::make($image->getRealPath());
                $img->resize(460, 340, function ($constraint) {
                    $constraint->aspectRatio();
                })->save($destinationPath . '/' . $filename);

                $poster = '/uploads/banner/' . $filename;
            }

            if ($request->offer_start) {
                $offerStart = \DateTime::createFromFormat('d/m/Y', $request->offer_start);
            } else {
                $offerStart = new \DateTime();
            }
            if ($request->offer_end) {
                $offerEnd = \DateTime::createFromFormat('d/m/Y', $request->offer_end);
            } else {
                $offerEnd = new \DateTime();
            }

            $coupon = Coupon::create([
                'code' => uniqid(),
                'name' => $request->name,
                'type' => 'banner',
                'user_id' => Auth::user()->id,
                'is_offer' => 1,
                'offer_start' => $offerStart->format('Y-m-d'),
                'offer_end' => $offerEnd->format('Y-m-d'),
                'poster' => $poster,
                'items' => null
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
                return redirect()->route('dashboard.banner.index')->with([
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
        $banner = Coupon::findOrFail($id);
        return view('admin.banner.show', compact('banner'))->with(['mappings'])->withTitle('Banner statistics');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $banner = Coupon::findOrFail($id);
        return view('admin.banner.edit', compact('banner'))->withTitle('Update banner');
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot update coupon'];
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|max:191,title',
            'description' => 'bail|nullable|string,content',
            'offer_start' => 'bail|required',
            'offer_end' => 'bail|required',
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
            if ($request->offer_start) {
                $offerStart = \DateTime::createFromFormat('d/m/Y', $request->offer_start);
            } else {
                $offerStart = new \DateTime();
            }
            if ($request->offer_end) {
                $offerEnd = \DateTime::createFromFormat('d/m/Y', $request->offer_end);
            } else {
                $offerEnd = new \DateTime();
            }
            $coupon = Coupon::findOrFail($id);
            $coupon->update([
                'name' => $request->name,
                'offer_start' => $offerStart->format('Y-m-d'),
                'offer_end' => $offerEnd->format('Y-m-d'),
                'type' => 'banner'
            ]);

            //upload poster/banner
            if ($request->file('poster')) {
                $image = $request->file('poster');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = public_path('uploads/banner/');
                $img = Image::make($image->getRealPath());
                $img->resize(460, 340, function ($constraint) {
                    $constraint->aspectRatio();
                })->save($destinationPath . '/' . $filename);

                $coupon->poster = '/uploads/banner/' . $filename;
            }

            if ($coupon->save()) {
                DB::commit();
                $data['content'] = 'Banner successfully updated';
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
                return redirect()->route('dashboard.banner.index')->with([
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
