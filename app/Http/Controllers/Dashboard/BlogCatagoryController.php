<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\Validator;
use App\Models\BlogCatagory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlogCatagoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $catagorys = BlogCatagory::get();
        return view('admin.blogcatagory.index', compact('catagorys'))->withTitle('Blog Catagory');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.blogcatagory.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => ''];
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|max:191,title',
            'content_body' => 'bail|required|string'
        ]);
        try {
            $catagory = new BlogCatagory;
            $catagory->title = $request->title;
            $catagory->description = $request->content_body;

            if ($catagory->save()) {
                DB::commit();
                $data['content'] = 'Blog Catagory successfully created';
                $data['label'] = 'success';
                $data['status'] = true;
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = 'Blog Catagory cannot be created';
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
                return redirect()->route('dashboard.blogcatagory.index')->with([
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $blog = BlogCatagory::find($id);
        //dd($blog);
        return view('admin.blogcatagory.edit', compact('blog'))->withTitle('Update Blog Catagory');
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
        $data = ['status' => false, 'label' => 'error', 'content' => ''];
        $blog = BlogCatagory::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|max:191,title',
            'content_body' => 'bail|required|string'
        ]);
        if ($validator->fails()) {
            $data['msg'] = $validator->errors()->first();

            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator->errors())->withInput();
            }
        }

        try {
            $blog->title = $request->title;
            $blog->description = $request->content_body;

            if ($blog->save()) {
                DB::commit();
                $data['content'] = 'Blog Catagory successfully Updated';
                $data['label'] = 'success';
                $data['status'] = true;
            }

        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = 'Blog catagory cannot be updated';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }

        return redirect()->route('dashboard.blogcatagory.index')->with([
            'message' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
