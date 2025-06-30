<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiSupportController extends Controller
{
    public $success = 200;
    public function store( Request $request )
    {
        $data = ['success' => false, 'message' => 'Cannot send message'];
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'bail|required|email|string',
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'subject' => 'bail|required|string',
            'message' => 'bail|required|string'
        ]);

        if( $validator->fails() == True ) {
            $data['message'] = $validator->errors()->first();
        } else {
            if(Support::create($request->all())) {
                $data['success'] = true;
                $data['message'] = 'Your message successfully sent';
            }
        }

        return response()->json($data, $this->success);
    }
}
