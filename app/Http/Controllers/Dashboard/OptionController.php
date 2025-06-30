<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\OptionUpdateJob;
use App\Models\Option;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\FirebaseService;

class OptionController extends Controller
{
    private $firebase;
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['title'] = 'Options';
        return view('admin.option.index',$data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
//        dd( $request );
        $label = '';
        $msg = '';
        if( isset( $_POST['tab'] ) ) :
            Cache::forget('options');
            $tab = $_POST['tab'];
            foreach( $_POST as $key => $value )
            {
                // if key == submit the skip
                if( $key != 'submit' && $key != 'tab' && $key != 'id' && $key != '_token')
                {
                    if( $this->_option_exist( $key ) ) {
                        $where = ['field' => $key];
                        $option = DB::table('options')
                            ->where( $where )
                            ->update( ['value' => $value, 'tab' => $tab ]);
                        // $option->content = $value;
                        // $option->save();
                            $msg = 'Option has been updated successfully.';
                            $label = 'success';
                    }else{
                        $option = new Option;
                        $option->tab = $tab;
                        $option->field = $key;
                        $option->value = $value;
                        $option->save();
                        if( $option ) :
                            $msg = 'Option has been updated successfully.';
                            $label = 'success';
                        else :
                            $label = 'error';
                            $msg = 'Sorry! Option cannot be updated.';
                        endif;
                    }
                }
            }

            dispatch(new OptionUpdateJob($this->firebase));
        else :
            $label = 'error';
            $msg = 'Sorry! Option cannot be updated.';
        endif;

        return redirect()->route('dashboard.option.index', ['tab' => $request->tab ])->with(['message.label' => $label, 'message.content' => $msg]);
    }

    public function _option_exist( $key )
    {
        $where = ['field' => $key];
        $option = Option::where( $where )->first();

        return ( $option ) ? true : false;
    }
}
