<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Notifications\EmailOtp;
use App\Models\User;
use App\Models\UserOtp;

set_time_limit(180);
ini_set('max_execution_time', 180);

class AuthController extends Controller
{
    public function login( Request $request )
    {
    	$data = ['status' => false, 'label' => 'error', 'content' => 'Account not foud'];
    	$validator = Validator::make($request->all(), [
    		'email' => 'bail|required|email|exists:users,email'
    	]);

    	//if validation faild
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

        $data['user'] = User::where('email', $request->email)->first();
        $code = mt_rand(100000,999999);
        if(App::environment('local')) {
            $code = '123456';
        }
        $otp = UserOtp::firstOrNew(['mobile' => $data['user']->mobile]);
        $otp->mobile = $data['user']->mobile;
        $otp->otp_code = $code;
        $otp->updated_at = now();
        if( $otp->save() ) {
        	\Session::put('login_check', [
        		'code' => $code,
        		'user' => $data['user']
        	]);
            if(App::environment('production')) {
                $data['user']->notify(new EmailOtp($code));
            }
            // Log::debug('OTP Code for ' . $data['user']->mobile . ' - ' . $code);
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'OTP successfully sent to your email';
        } else {
        	$data['content'] = 'Could not send code';
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success );
        } else {
            return redirect()->route('auth.check')->with([
                'message' => $data
            ])->withInput($request->all());
        }
    }

    public function check( Request $request )
    {
    	if( \Session::has('login_check') ) {
    		$session = \Session::get('login_check');
    		$user = $session['user'];
    		return view('auth.check', compact('user'));
    	} else {
    		return redirect()->to('/login');
    	}
    }

    public function verify( Request $request )
    {
    	$data = ['success' => false, 'label' => 'error', 'message' => 'Cannot verify user'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'email' => 'bail|nullable|email|exists:users,email',
            'otp_code' => 'bail|nullable|max:6|exists:user_otps,otp_code',
        ]);

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

        $user = User::where('email', $request->email)->first();

        $otp = UserOtp::where(['mobile' => $user->mobile, 'otp_code' => $request->otp_code])->first();

        if( $otp && strtotime( $otp->updated_at ) >= ( time() - 900) ) {
        	Auth::login( $user );
        	$data['status'] = true;
        	$data['label'] = 'success';
        	$data['content'] = 'You are successfully logged in';
        } else {
        	$data['content'] = 'Your otp is not valid or expired';
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success );
        } else {
            return redirect()->route('home')->with([
                'message' => $data
            ])->withInput($request->all());
        }
    }
}
