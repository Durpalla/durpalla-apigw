<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Blog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $blogs = Blog::with(['blogcatagory'])->get();
        return view('admin.blog.index', compact('blogs'))->withTitle('Blogs');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $catagorys = BlogCatagory::get();
        return view('admin.blog.create', compact('catagorys'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot create blog'];
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|max:191,title',
            'contetnt' => 'bail|required|string,content',
            'logo' => 'required|mimes:png,jpg,jpeg|max:150'
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
            $blog = new Blog();
            $blog->title = $request->title;
            $blog->body = $request->body;
            $blog->catagory_id = $request->catagory;

            if ($request->bimgs) {
                $imageName = time() . '.' . $request->bimgs->extension();
                $request->bimgs->move(public_path('images'), $imageName);
                $blog->logo = $imageName;
            }
            if ($blog->save()) {
                DB::commit();
                $data['content'] = 'Blog successfully created';
                $data['label'] = 'success';
                $data['status'] = true;
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->route('dashboard.page.index')->with([
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
        $blog = Blog::find($id);
        return view('admin.blog.show', compact('blog'))->withTitle('View blog');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $blog = Blog::find($id);
        return view('admin.blog.edit', compact('blog'))->withTitle('Update Blog');
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
        $blog = Blog::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'title' => 'bail|required|string|max:191,title',
            'content_body' => 'bail|required|string'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();

            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator->errors())->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $blog->title = $request->title;
            $blog->body = $request->content_body;

            if ($request->logo) {
                $imageName = time() . '.' . $request->logo->extension();
                $request->logo->move(public_path('images'), $imageName);
                $blog->image = $imageName;
            }

            if ($blog->save()) {
                DB::commit();
                $data['content'] = 'Blog successfully Updated';
                $data['label'] = 'success';
                $data['status'] = true;
            }

        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = 'Blog account cannot be updated';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }

        return redirect()->route('dashboard.blog.index')->with([
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
