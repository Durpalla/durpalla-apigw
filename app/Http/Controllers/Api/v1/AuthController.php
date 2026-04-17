<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use App\Helpers\LogHelper;
use App\Http\Requests\LoginCheckRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpVerifyRequest;
use App\Http\Requests\RegisterRequest;
use Exception;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Events\UserCreated;

class AuthController extends Controller
{
    private int $success;

    public function __construct()
    {
        parent::__construct();
        $this->success = 200;
    }

    public function check(LoginCheckRequest $request)
    {
        $data = ['success' => false, 'message' => __('Something went wrong. Please try again.')];

        $account = User::where('type', AppConst::USER_TYPE_CUSTOMER)
            ->where('mobile', $request->mobile)
            ->first();

        if ($account) {
            if ($account->email_verified_at == null || $account->status == 0) {

                $code = mt_rand(100000, 999999);
                if (App::environment('local')) {
                    $code = AppConst::DEFAULT_OTP;
                }
                $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
                if ($otp) {
                    if (strtotime($otp->updated_at) < (time() - 900)) {
                        $otp->otp_code = $code;
                        $otp->attempts = 1;
                    } elseif ($otp->attempts >= 15) {
                        return response()->json(['success' => false, 'message' => __('You have already tried more than 5 time.')], $this->success);
                    } else {
                        $otp->attempts += 1;
                    }
                } else {
                    $otp->mobile = $request->mobile;
                    $otp->attempts = 1;
                }
                $otp->updated_at = now();

                if ($otp->save() && !app()->environment('local')) {
                    dispatch(new OTPCodeSendingJob($request->mobile, $otp->otp_code));
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => config('app.name') . ' verification code is ' . $otp->otp_code
                    ]);
                    // Log::debug('OTP Code for ' . $request->mobile . ' - ' . $otp->otp_code);
                }

                $data['success'] = true;
                $data['message'] = __('Customer account not verified');
                $data['step'] = 'otp';
            } elseif ($account && $account->status == 1) {
                $data['success'] = true;
                $data['message'] = __('Customer account found');
                $data['step'] = 'login';
            } else {
                $data['success'] = false;
                $data['message'] = __('Customer account is not active');
                $data['step'] = 'check';
            }

        } else {
            $code = AppConst::DEFAULT_OTP;
            if (App::environment('production')) {
                $code = mt_rand(100000, 999999);
            }
            $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
            $otp->mobile = $request->mobile;
            $otp->otp_code = $code;
            $otp->verified = 0;
            if ($otp) {
                if (strtotime($otp->updated_at) < (time() - 900)) {
                    $otp->otp_code = $code;
                    $otp->attempts = 1;
                } elseif ($otp->attempts >= 15) {
                    return response()->json(['success' => false, 'message' => __('You have already tried more than 5 time.')], $this->success);
                } else {
                    $otp->attempts += 1;
                }
            }
            $otp->updated_at = now();
            if ($otp->save()) {
                sendSMS([
                    'mobile' => $request->mobile,
                    'message' => config('app.name') . ' verification code is ' . $otp->otp_code
                ]);
//                    Log::debug('OTP Code for ' . $request->mobile . ' - ' . $otp->otp_code);
                $data['success'] = true;
                $data['message'] = __('Customer account not found');
                $data['step'] = 'otp';
            }
        }

        return response()->json($data, $this->success);
    }

    public function verify(OtpVerifyRequest $request)
    {
        $data = ['success' => false, 'message' => __('Cannot verify OTP')];

        try {
            $otp = UserOtp::where('mobile', $request->mobile)
                ->where('otp_code', $request->otp)
                ->where('type', $request->type ? $request->type : 'login')
                ->latest()
                ->first();
            if (!$otp) {
                $data['message'] = 'Your OTP code is invalid.';
                return $data;
            }
                if (strtotime($otp->updated_at) < time() - 900) {
                    $data['message'] = 'Your otp code has been expired.';
                } else {
                    $user = User::firstOrNew([
                        'mobile' => $request->mobile,
                        'type' => AppConst::USER_TYPE_CUSTOMER
                    ]);

                    if ($user->id) {
                        $user->email_verified_at = now();
                        $user->save();
                        $data['step'] = 'login';
                    } else {
                        $data['step'] = 'register';
                    }

                    if ($request->type == 'forgot') {
                        $data['step'] = 'reset';
                    }

                    $data['success'] = true;
                    $data['message'] = __('Your otp has been verified');
                }

                $otp->verified = 1;
                $otp->save();
        } catch (\Exception $e) {
            LogHelper::exception($e, [
                'keyword' => 'OTP_VERIFY_EXCEPTION'
            ]);
            $data['message'] = __('Internal error!');
        }
        //send data with success
        return response()->json($data, $this->success);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot register account.')];
        try {
            $otp = UserOtp::where(['mobile' => $request->mobile, 'verified' => 1])->first();

            if ($otp) {
                DB::beginTransaction();
                try {
                    $user = new User;
                    $user->name = $request->name;
                    $user->email = $request->email;
                    $user->mobile = $request->mobile;
                    $user->nid = $request->nid;
                    $user->password = Hash::make($request->password);
                    $user->email_verified_at = now();
                    $user->type = AppConst::USER_TYPE_CUSTOMER;
                    $user->device_id = $request->device_id;

                    $user->save();
                    $platform = ($request->platform) ? $request->platform : 'web';
                    event(new UserCreated($user, $platform));
                    DB::commit();
                    $token = $user->createToken(config('app.name'))->accessToken;
                    // $token = Str::random(80);

                    //refined UserData
                    $userData = array(
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'type' => $user->type,
                        'photo' => $user->profile_pic ? asset($user->profile_pic) : asset('default/avatar.png')
                    );

                    $data['user'] = $userData;
                    $data['token'] = $token;
                    $data['success'] = true;
                    $data['message'] = __('You have successfully registered');
                } catch (\Exception $e) {
                    DB::rollback();
                    Log::debug($e->getMessage());
                }
            } else {
                $data['message'] = __('Sorry! verification failed.');
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'REGISTER_EXCEPTION'
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    public function login(LoginRequest $request)
    {
        try {
            //check if an account exists or not
            $user = User::where('type', AppConst::USER_TYPE_CUSTOMER)
                ->where(['mobile' => $request->mobile])
                ->first();

            if (empty($user))
                return response()->json(['success' => false, 'message' => __('Account not found.')], $this->success);

            if ($user->email_verified_at == null) {
                $code = mt_rand(100000, 999999);
                $otp = UserOtp::firstOrNew(['mobile' => $request->mobile]);
                if ($otp) {
                    if (strtotime($otp->updated_at) < (time() - 900)) {
                        $otp->otp_code = $code;
                        $otp->attempts = 1;
                    } elseif ($otp->attempts >= 15) {
                        return response()->json(['success' => false, 'message' => __('You have already tried 5 time. please try after few times.')], $this->success);
                    } else {
                        $otp->attempts += 1;
                    }
                } else {
                    $otp->mobile = $request->mobile;
                    $otp->attempts = 1;
                }
                $otp->updated_at = now();

                if ($otp->save()) {
//                sendSMS([
//                    'mobile' => $request->mobile,
//                    'message' => 'Your otp code is ' . $otp->otp_code
//                ]);
                }
                return response()->json(['success' => false, 'otp_required' => true, 'message' => __('Your account need to verified')], $this->success);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json(['success' => false, 'message' => __('Your password does not match.')], $this->success);
            }

            //update device id
            $user->device_id = $request->device_id;
            $user->save();

            $token = $user->createToken(config('app.name'))->accessToken;
            //refined UserData
            $userData = array(
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'type' => $user->type,
                'photo' => $user->profile_pic ? asset($user->profile_pic) : asset('default/avatar.png'),
                'vat_visibility' => $user->type == 'merchant' && $user->merchant['vat_visibility'] == '1',
                'nid_verification' => ($user->meta) ? $user->meta->nid_verified : 0,
                'nid' => null,
                'vehicle_type' => 'all'
            );
            if ($user->type === 'customer' && $user->meta && $user->meta['nid_no']) {
                $userData['nid'] = [
                    'nid_no' => $user->meta['nid_no'],
                    'front' => ($user->meta['nid_photo']) ? asset('nid/' . $user->meta['nid_photo']) : '',
                    'back' => ($user->meta['nid_back_side']) ? asset('nid/' . $user->meta['nid_back_side']) : ''
                ];
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'LOGIN_EXCEPTION'
            ]);
            return response()->json(['success' => false, 'message' => __('Internal server error!')], $this->success);
        }

        //send data with success
        return response()->json(['success' => true, 'message' => __('Login success'), 'token' => $token, 'user' => $userData], $this->success);
    }

    public function resendCode(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot re-send code')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:user_otps,mobile'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $code = mt_rand(100000, 999999);
                $otps = UserOtp::where(['mobile' => $request->mobile])->first();
                if (strtotime($otps->updated_at) > time() - 900) {
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
                } elseif ($otps->attempts >= 5) {
                    $data['success'] = false;
                    $data['message'] = __('You have tried more than 5 times.');
                } else {
                    $otps->updated_at = now();
                    $otps->attempts += 1;
                    $otps->save();

                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $otps->otp_code
                    ]);
                    $data['success'] = true;
                    $data['message'] = __('OTP code successfully sent');
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollback();
            }
        }

        return response()->json($data, $this->success);
    }

    public function bindPush(Request $request)
    {
        $data = ['status' => false, 'message' => 'Failed'];
        try {
            $request->user()->deviceToken()->updateOrCreate(
                [
                    'platform' => $request->platform
                ],
                [
                    'token' => $request->token
                ]
            );
            $data['status'] = true;
            $data['message'] = 'Success';
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'AUTH_PUSH_BIND_EXCEPTION'
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    private function _account_exist_by_mobile($mobile)
    {
        $query = User::where(['mobile' => $mobile])->first();

        return !empty($query);
    }

    public function forgot(Request $request)
    {
        $data = ['success' => false, 'message' => __('User account not found')];
        try {
            //validation rules
            $validator = Validator::make($request->all(), [
                'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:users,mobile'
            ]);

            //validation fails
            if ($validator->fails()) {
                $data['message'] = $validator->errors()->first();
            } else {
                $code = AppConst::DEFAULT_OTP;
                if (app()->environment('production')) {
                    $code = mt_rand(100000, 999999);
                }
                $otp = UserOtp::firstOrNew(['mobile' => $request->mobile, 'type' => 'forgot']);
                $otp->mobile = $request->mobile;
                $otp->otp_code = $code;
                if ($otp->save()) {
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $code
                    ]);
                    $data['success'] = true;
                    $data['step'] = 'forgot_otp';
                    $data['message'] = __('An otp code has been sent to mobile.');
                }
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'FORGOT_PASSWORD_EXCEPTION'
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    public function reset(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot verify user')];

        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:users,mobile',
            'password' => 'bail|nullable|min:8|max:20',
            'confirm_password' => 'bail|required|min:8|max:20|same:password'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $user = User::where('type', AppConst::USER_TYPE_CUSTOMER)
                    ->where('mobile', $request->mobile)
                    ->first();
                $user->password = Hash::make($request->password);
                $user->save();
                DB::commit();

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
                    'role' => ($user->roles != null) ? $user->roles[0]->name : 'unknown'
                );

                $data['success'] = true;
                $data['message'] = __('Your password has been reset.');
            } catch (Exception $e) {
                DB::rollback();
                $data['content'] = __('Your password cannot be changed.');
            }
        }

        //send data with success
        return response()->json($data, $this->success);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $user->token()->revoke();

        return response()->json(['success' => true, 'message' => __('You are successfully logout')], $this->success);
    }
}
