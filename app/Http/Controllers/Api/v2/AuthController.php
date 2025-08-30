<?php

namespace App\Http\Controllers\Api\v2;

use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\OTPCodeSendingJob;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Log;
use Lang;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Events\UserCreated;

class AuthController extends Controller
{
    private $status;
    private $success;

    public function __construct() {
        $this->status = 200;
        $this->success = 200;
    }

    /**
     * check the customer exist by mobile.
     *
     * @return JsonResponse
     */
    public function check( Request $request )
    {
        $data = ['success'=> false, 'message' => __('Something went wrong. Please try again.')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $account = User::where('mobile', $request->mobile)->first();

            if( $account ) {
                if( $account->email_verified_at == null || $account->status == 0 ) {

                    $code = mt_rand(100000,999999);
                    if(App::environment('local')) {
                        $code = '123456';
                    }
                    $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
                    if( $otp ) {
                        if( strtotime( $otp->updated_at ) < ( time() - 900) ) {
                            $otp->otp_code = $code;
                            $otp->attempts = 1;
                        } elseif( $otp->attempts >= 15) {
                            return response()->json(['success' => false, 'message' => __('You have already tried more than 5 time.')], $this->success );
                        } else {
                            $otp->attempts += 1;
                        }
                    } else {
                        $otp->mobile = $request->mobile;
                        $otp->attempts = 1;
                    }
                    $otp->updated_at = now();

                    if( $otp->save() ) {
                        $this->dispatch(new OTPCodeSendingJob($request->mobile, $otp->otp_code));
                            sendSMS([
                                'mobile' => $request->mobile,
                                'message' => config('app.name') . ' verification code is ' . $otp->otp_code
                            ]);
                        // Log::debug('OTP Code for ' . $request->mobile . ' - ' . $otp->otp_code);
                    }

                    $data['success'] = true;
                    $data['message'] = __('Customer account not verified');
                    $data['step'] = 'register';
                } elseif($account && $account->status == 1) {
                    $data['success'] = true;
                    $data['message'] = __('Customer account found');
                    $data['step'] = 'login';
                } else {
                    $data['success'] = false;
                    $data['message'] = __('Customer account is not active');
                    $data['step'] = 'check';
                }

            } else {
                $code = mt_rand(100000,999999);
                if(App::environment('local')) {
                    $code = '123456';
                }
                $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
                $otp->mobile = $request->mobile;
                if( $otp ) {
                    if( strtotime( $otp->updated_at ) < ( time() - 900) ) {
                        $otp->otp_code = $code;
                        $otp->attempts = 1;
                    } elseif( $otp->attempts >= 15) {
                            return response()->json(['success' => false, 'message' => __('You have already tried more than 5 time.')], $this->success );
                    } else {
                        $otp->attempts += 1;
                    }
                }
                $otp->updated_at = now();
                if( $otp->save() ) {
//                    sendSMS([
//                        'mobile' => $request->mobile,
//                        'message' => config('app.name') . ' verification code is ' . $otp->otp_code
//                    ]);
//                    Log::debug('OTP Code for ' . $request->mobile . ' - ' . $otp->otp_code);
                    $data['success'] = true;
                    $data['message'] = __('Customer account not found');
                    $data['step'] = 'otp';
                }
            }
        }

        return response()->json($data, $this->success );
    }

    /**
     * Verify the customer.
     *
     * @return JsonResponse
     */
    public function verify( Request $request )
    {
        $data = ['success' => false, 'message' => __('Cannot verify mobile')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'otp' => 'bail|nullable|max:6|exists:user_otps,otp_code',
            'type' => 'nullable'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $otp = UserOtp::where(['mobile' => $request->mobile, 'otp_code' => $request->otp])->first();
            if( $otp ) {
                if( strtotime( $otp->updated_at ) < time() - 900 ) {
                    $data['message'] = 'Your otp code has been expired.';
                } else {
                    $user = User::firstOrNew(['mobile' => $request->mobile]);

                    if( $user->id ) {
                        $user->email_verified_at = now();
                        $user->save();
                        $data['step'] = 'login';
                    } else {
                        $data['step'] = 'register';
                    }

                    if( $request->type == 'forgot' ) {
                        $data['step'] = 'reset';
                    }

                    $data['success'] = true;
                    $data['message'] = __('Your otp has been verified');
                }

                $otp->verified = 1;
                $otp->save();
            }
        }
        //send data with success
        return response()->json( $data, $this->success );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register( Request $request ): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot register account.')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:191|min:3',
            'email' => 'bail|required|max:191|email|unique:users,email',
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:users,mobile',
            'nid' => 'bail|required|min:10|max:17|numeric|unique:users,nid',
            'password' => 'bail|required|min:8|max:20',
            'confirm_password' => 'bail|required|min:8|max:20|same:password',
            'platform' => 'bail|nullable',
            'device_id' => 'bail|nullable|string'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $otp = UserOtp::where(['mobile' => $request->mobile, 'verified' => 0])->first();

            if( $otp ) {
                DB::beginTransaction();
                try {
                    $user = new User;
                    $user->name = $request->name;
                    $user->email = $request->email;
                    $user->mobile = $request->mobile;
                    $user->nid = $request->nid;
                    $user->password = Hash::make( $request->password );
                    $user->email_verified_at = now();
                    $user->type = 'customer';
                    $user->device_id = $request->device_id;

                    $user->save();
                    $role = Role::where('name', 'customer')->first();
                    $user->assignRole($role);
                    $platform = ( $request->platform ) ? $request->platform : 'web';
                    event(new UserCreated($user, $platform));
                    DB::commit();
                    //if password matched then create authenticate
                    Auth::login( $user );

                    //create / Generate Access Token

                    $token = $user->createToken(config('app.name'))->accessToken;
                    // $token = Str::random(80);

                    //refined UserData
                    $userData = array(
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'type' => $user->type,
                        'role' => 'customer',
                        'photo' => $user->profile_pic ? asset($user->profile_pic) : asset('default/avatar.png')
                    );

                    $otp->revoked();
                    $data['user'] = $userData;
                    $data['token'] = $token;
                    $data['success'] = true;
                    $data['message'] = __('You have successfully registered');
                } catch( \Exception $e ) {
                    DB::rollback();
                    Log::debug( $e->getMessage());
                    $data['success'] = false;
                    $data['message'] = $e->getMessage();
                }
            } else {
                $data['message'] = __('Sorry! verification failed.');
            }
        }

        return response()->json($data, $this->success );
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function login( Request $request )
    {
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|nullable|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'password' => 'bail|required|min:8|max:20',
            'device_id' => 'bail|string|max:191'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        //check if account exist of not
        $user = User::with(['roles'])->where(['mobile' => $request->mobile])->first();

        if( empty( $user ) )
            return response()->json(['success' => false, 'message' => __('Account not found.')], $this->success );
        //check password is matched
        // return Hash::make( '123456' );
        // return $user->password;

        // return response()->json( $request->password );

        if( $user->email_verified_at == null ) {
            $code = mt_rand(100000,999999);
            $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
            if( $otp ) {
                if( strtotime( $otp->updated_at ) < ( time() - 900) ) {
                    $otp->otp_code = $code;
                    $otp->attempts = 1;
                } elseif( $otp->attempts >= 15) {
                    return response()->json(['success' => false, 'message' => __('You have already tried 5 time. please try after few times.')], $this->success );
                } else {
                    $otp->attempts += 1;
                }
            } else {
                $otp->mobile = $request->mobile;
                $otp->attempts = 1;
            }
            $otp->updated_at = now();

            if( $otp->save() ) {
//                sendSMS([
//                    'mobile' => $request->mobile,
//                    'message' => 'Your otp code is ' . $otp->otp_code
//                ]);
            }
            return response()->json(['success' => false, 'otp_required' => true, 'message' => __('Your account need to verified')], $this->success );
        }

        if( !Hash::check( $request->password, $user->password ) ) {
            return response()->json(['success' => false, 'message' => __('Your password does not match.')], $this->success );
        }

        //update device id
        $user->device_id = $request->device_id;
        $user->save();

        //if password matched then create authenticate
        Auth::login( $user );

        //create / Generate Access Token

        $token = $user->createToken(config('app.name'))->accessToken;

        //logout other devices for specefic role
        if($user->hasAnyRole(['supervisor', 'admin', 'manager'])) {
            auth()->logoutOtherDevices(request()->password);
        }


        //refined UserData
        $userData = array(
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'type' => $user->type,
            'photo' => $user->profile_pic ? asset($user->profile_pic) : asset('default/avatar.png'),
            'role' => $user->hasAnyRole(['supervisor', 'agent', 'partner']) ? $user->roles->first()->name : 'customer',
            'vat_visibility' => $user->type == 'merchant' && $user->merchant['vat_visibility'] == '1',
            'nid_verification' => ($user->hasRole('customer') && $user->meta) ? $user->meta->nid_verified : 0,
            'nid' => null,
            'vehicle_type' => 'all'
        );
        if($user->type === 'customer' && $user->meta && $user->meta['nid_no']) {
            $userData['nid'] = [
                'nid_no' => $user->meta['nid_no'],
                'front' => ($user->meta['nid_photo']) ? asset('nid/' . $user->meta['nid_photo']) : '',
                'back' => ($user->meta['nid_back_side']) ? asset('nid/' . $user->meta['nid_back_side']) : ''
            ];
        }

        if($user->hasRole('supervisor') && $user->type === AppConst::TYPE_MERCHANT) {
            $userData['vehicle_type'] = ($user->vehicles->count()) ? $user->vehicles->first()->vehicle->vehicle_type : null;
        }

        //send data with success
        return response()->json(['success' => true, 'message' => __('Login success'), 'token' => $token, 'user' => $userData ], $this->success );
    }

    public function resendCode( Request $request )
    {
        $data = ['success'=> false, 'message' => __('Cannot re-send code')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:user_otps,mobile'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try{
                $code = mt_rand(100000,999999);
                $otps = UserOtp::where(['mobile' => $request->mobile])->first();
                if( strtotime( $otps->updated_at ) > time() - 900 ) {
                    $otps->otp_code = $code;
                    $otps->updated_at = now();
                    $otps->attempts = 1;
                    $otps->save();
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $otps->otp_code
                    ]);
//                    Log::debug('Your otp code is ' . $otps->otp_code);
                    $data['success'] = true;
                    $data['message'] = __('OTP code successfully sent');
                } elseif( $otps->attempts >= 5 ) {
                    $data['success'] = false;
                    $data['message'] = __('You have tried more than 5 times.');
                } else {
                    $otps->updated_at = now();
                    $otps->attempts += 1;
                    $otps->save();

                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $otps->otp_code
                    ]);                    $data['success'] = true;
                    $data['message'] = __('OTP code successfully sent');
                }

                DB::commit();

            } catch( \Exception $e ){
                DB::rollback();
            }
        }

        return response()->json($data, $this->success);
    }

    private function _account_exist_by_mobile( $mobile )
    {
        $query = User::where(['mobile' => $mobile])->first();

        return ( !empty( $query ) ) ? true : false;
    }

    /**
     * Forgot password.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function forgot( Request $request )
    {
        $data = ['success'=> false, 'message' => __('User account not found')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:users,mobile'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $code = mt_rand(100000,999999);
            $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
            $otp->mobile = $request->mobile;
            $otp->otp_code = $code;
            if( $otp->save() ) {
                sendSMS([
                    'mobile' => $request->mobile,
                    'message' => 'Your otp code is ' . $code
                ]);
                $data['success'] = true;
                $data['step'] = 'forgot_otp';
                $data['message'] = __('An otp code has been sent to mobile.');
            }
        }

        return response()->json($data, $this->success );
    }

    /**
     * Verify the customer.
     *
     * @return JsonResponse
     */
    public function reset( Request $request )
    {
        $data = ['success' => false, 'message' => __('Cannot verify user')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:users,mobile',
            'password' => 'bail|nullable|min:8|max:20',
            'confirm_password' => 'bail|required|min:8|max:20|same:password'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try{
                $user = User::where('mobile', $request->mobile)->first();
                $user->password = Hash::make( $request->password );
                $user->save();
                DB::commit();

                //if password matched then create authenticate
                Auth::login( $user );

                //create / Generate Access Token

                $data['token'] = $user->createToken(config('app.name'))->accessToken;
                // $token = Str::random(80);

                //refined UserData
                $data['user'] = array(
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'type' => $user->type,
                    'photo' => $user->profile_pic ? asset($user->profile_pic) : asset('default/avatar.png'),
                    'role' => ( $user->roles != null ) ? $user->roles[0]->name : 'unknown'
                );

                $data['success'] = true;
                $data['message'] = __('Your password has been reset.');
            } catch(Exception $e) {
                DB::rollback();
                $data['content'] = __('Your password cannot be changed.');
            }
        }

        //send data with success
        return response()->json( $data, $this->success );
    }

    /**
     * Logout user.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function logout( Request $request )
    {
        $user = Auth::user();
        $user->token()->revoke();

        return response()->json(['success' => true, 'message' => __('You are successfully logout')], $this->success );
    }
}
