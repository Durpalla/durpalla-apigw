<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Page;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pages = Page::get();
        return view('admin.page.index', compact('pages'))->withTitle('Page Managment');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('admin.page.create')->withTitle('Create New Item');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Page cannot be created'];
        $validator = Validator::make($request->all(), [
            'title'=>'bail|required|string|max:191,title|unique:pages,title',
            'body' => 'bail|required|string'
        ]);

        /*dd($request);*/
        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput($request->all());
            }
        }

        DB::beginTransaction();
        try{
            //proecss request

                $page = new Page;
                $page->title = $request->title;
                $page->slug = ( $request->slug ) ? $request->slug : niceSlug($request->title);
                $page->content = $request->body;
                if( $page->save() ) {
                    DB::commit();
                    $data['content'] = 'Page has been successfully created';
                    $data['label'] = 'success';
                    $data['status'] = true;
                }
            }
         catch(\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }


        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        $page = Page::find( $id );
        return view('admin.page.edit', compact('page'))->withTitle('Edit page');
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
        //
        $data = ['status' => false, 'label' => 'error', 'content' => 'Page cannot be updated'];
        $page  = Page::findOrFail( $id );
        $validator = Validator::make($request->all(), [
            'title'=>'bail|required|string|max:191,title|unique:pages,title,' . $id,
            'body' => 'bail|required|string'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();

            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator->errors())->withInput();
            }
        }

       try{
            //proecss request
            DB::transaction(function() use($request, &$data, &$page) {
                $page ->title = $request->title;
                $page ->content = $request->body;
                if( $page ->save() ) {
                    DB::commit();
                    $data['content'] = 'Page contetnt successfully Updated';
                    $data['label'] = 'success';
                    $data['status'] = true;
                }
            });
        } catch(\Exception $e) {
            $data['content'] = 'Error occured! Page cannot be updated';
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        }

        return redirect()->route('dashboard.page.index')->with([
            'message' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $page = Page::findOrFail($id);
        $page->delete();
        return redirect()->route('dashboard.page.index')->with([
            'message' => ['status' => true, 'label' => 'success', 'content' => 'Your page has been successfully deleted']
        ]);
    }
}
