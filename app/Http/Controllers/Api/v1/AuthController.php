<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use App\Events\UserCreated;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginCheckRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpVerifyRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Customer;
use App\Models\UserOtp;
use App\Services\CartService;
use App\Services\TwoFactorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Customer auth for mobile/web apps – Customer model + Sanctum (guard: customer).
 */
class AuthController extends Controller
{
    private int $success;

    public function __construct()
    {
        parent::__construct();
        $this->success = 200;
    }

    /** OTP code: fixed in non-production, random in production. */
    private function getOtpCode(): string
    {
        return App::environment('production')
            ? (string) mt_rand(100000, 999999)
            : (string) AppConst::DEFAULT_OTP;
    }

    /** Issue a Sanctum token and the standard customer payload after the second factor passes. */
    private function completeCustomerLogin(Customer $user): array
    {
        Auth::guard('customer')->setUser($user);
        $token = $user->createToken(config('app.name'))->plainTextToken;
        app(CartService::class)->claimGuestLocksForUser($user);

        return [
            'success' => true,
            'step' => 'authenticated',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'type' => 'customer',
                'photo' => $user->profile_pic ? upload_asset($user->profile_pic) : asset('default/avatar.png'),
                'role' => 'customer',
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'two_factor_method' => $user->hasTwoFactorEnabled() ? $user->twoFactorMethod() : null,
            ],
            'message' => __('Login success'),
        ];
    }

    public function check(LoginCheckRequest $request)
    {
        $data = ['success' => false, 'message' => __('Something went wrong. Please try again.')];

        $account = Customer::where('mobile', $request->mobile)->first();

        if ($account) {
            if ($account->email_verified_at == null || $account->status == 0) {
                $code = $this->getOtpCode();
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

                if ($otp->save() && ! app()->environment('local')) {
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => config('app.name') . ' verification code is ' . $otp->otp_code,
                    ]);
                }

                $data['success'] = true;
                $data['message'] = __('Customer account not verified');
                $data['step'] = 'otp';
            } elseif ($account->status == 1) {
                $data['success'] = true;
                $data['message'] = __('Customer account found');
                $data['step'] = 'login';
            } else {
                $data['success'] = false;
                $data['message'] = __('Customer account is not active');
                $data['step'] = 'check';
            }
        } else {
            $code = $this->getOtpCode();
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
                    'message' => config('app.name') . ' verification code is ' . $otp->otp_code,
                ]);
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
            $type = $request->input('type');

            // Authenticator-app users have no stored OTP row — check the TOTP code instead.
            if (in_array((string) $type, ['2fa_login', '2fa'], true)) {
                $totpUser = Customer::where('mobile', $request->mobile)->first();
                if ($totpUser && $totpUser->hasTwoFactorEnabled() && $totpUser->usesAuthenticatorApp()) {
                    $valid = app(TwoFactorService::class)
                        ->verifyTotp($totpUser->two_factor_secret, (string) $request->input('otp'));

                    if (! $valid) {
                        $data['message'] = __('That authenticator code is invalid or expired.');

                        return response()->json($data, $this->success);
                    }

                    return response()->json($this->completeCustomerLogin($totpUser), $this->success);
                }
            }

            $query = UserOtp::where('mobile', $request->mobile)
                ->where('otp_code', $request->input('otp'));

            // check() stores OTP without type (null). Flutter sends type=register for sign-up flow.
            if ($type === 'forgot') {
                $query->where('type', 'forgot');
            } elseif ($type === '2fa_login' || $type === '2fa') {
                $query->whereIn('type', ['2fa_login', '2fa']);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('type')
                        ->orWhere('type', '')
                        ->orWhere('type', 'login')
                        ->orWhere('type', 'register')
                        ->orWhere('type', '2fa_login')
                        ->orWhere('type', '2fa');
                });
            }

            $otp = $query->latest()->first();
            if (! $otp) {
                $data['message'] = 'Your OTP code is invalid.';

                return response()->json($data, $this->success);
            }

            if (strtotime($otp->updated_at) < time() - 900) {
                $data['message'] = 'Your otp code has been expired.';
            } else {
                $user = Customer::where('mobile', $request->mobile)->first();

                if ($user) {
                    $user->email_verified_at = now();
                    $user->save();
                    $data['step'] = 'login';
                } else {
                    $data['step'] = 'register';
                }

                if ($type == 'forgot') {
                    $data['step'] = 'reset';
                }

                $data['success'] = true;
                $data['message'] = __('Your otp has been verified');

                $otp->verified = 1;
                $otp->save();

                // Complete password+OTP login when 2FA is enabled.
                if (
                    $user
                    && (
                        in_array((string) $otp->type, ['2fa_login', '2fa'], true)
                        || $type === '2fa_login'
                        || $type === '2fa'
                    )
                    && $user->hasTwoFactorEnabled()
                ) {
                    $data = array_merge($data, $this->completeCustomerLogin($user));
                }
            }
        } catch (\Exception $e) {
            LogHelper::exception($e, [
                'keyword' => 'OTP_VERIFY_EXCEPTION',
            ]);
            $data['message'] = __('Internal error!');
        }

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
                    $user = new Customer;
                    $user->name = $request->name;
                    $user->email = $request->email;
                    $user->mobile = $request->mobile;
                    $user->password = Hash::make($request->password);
                    $user->email_verified_at = now();
                    $user->status = 1;

                    $user->save();
                    $platform = ($request->platform) ? $request->platform : 'web';
                    try {
                        event(new UserCreated($user, $platform));
                    } catch (\Throwable $e) {
                        // UserCreated historically typed for User; customers are separate now.
                    }
                    DB::commit();

                    Auth::guard('customer')->setUser($user);
                    $token = $user->createToken(config('app.name'))->plainTextToken;
                    app(CartService::class)->claimGuestLocksForUser($user);

                    $userData = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'type' => 'customer',
                        'role' => 'customer',
                        'photo' => $user->profile_pic ? upload_asset($user->profile_pic) : asset('default/avatar.png'),
                    ];

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
                'keyword' => 'REGISTER_EXCEPTION',
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = Customer::where(['mobile' => $request->mobile])->first();

            if (empty($user)) {
                return response()->json(['success' => false, 'message' => __('Account not found.')], $this->success);
            }

            if ($user->email_verified_at == null) {
                $code = $this->getOtpCode();
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
                $otp->save();

                return response()->json(['success' => false, 'otp_required' => true, 'message' => __('Your account need to verified')], $this->success);
            }

            if (! Hash::check($request->password, $user->password)) {
                return response()->json(['success' => false, 'message' => __('Your password does not match.')], $this->success);
            }

            if ($user->hasTwoFactorEnabled()) {
                $twoFactor = app(TwoFactorService::class);

                if ($user->usesAuthenticatorApp()) {
                    return response()->json([
                        'success' => true,
                        'step' => 'otp',
                        'otp_required' => true,
                        'two_factor' => true,
                        'two_factor_method' => TwoFactorService::METHOD_TOTP,
                        'message' => __('Enter the 6-digit code from your authenticator app.'),
                    ], $this->success);
                }

                if (! $twoFactor->sendEmailCode($user, '2fa_login')) {
                    return response()->json(['success' => false, 'message' => __('Could not send the login code to your email.')], $this->success);
                }

                return response()->json([
                    'success' => true,
                    'step' => 'otp',
                    'otp_required' => true,
                    'two_factor' => true,
                    'two_factor_method' => TwoFactorService::METHOD_EMAIL,
                    'message' => __('Enter the code we emailed you to finish login.'),
                ], $this->success);
            }

            Auth::guard('customer')->setUser($user);
            $token = $user->createToken(config('app.name'))->plainTextToken;
            app(CartService::class)->claimGuestLocksForUser($user);

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'type' => 'customer',
                'photo' => $user->profile_pic ? upload_asset($user->profile_pic) : asset('default/avatar.png'),
                'role' => 'customer',
                'vat_visibility' => false,
                'nid_verification' => ($user->meta) ? $user->meta->nid_verified : 0,
                'nid' => null,
                'vehicle_type' => 'all',
            ];
            if ($user->meta && $user->meta['nid_no']) {
                $userData['nid'] = [
                    'nid_no' => $user->meta['nid_no'],
                    'front' => ($user->meta['nid_photo']) ? upload_asset('nid/' . $user->meta['nid_photo']) : '',
                    'back' => ($user->meta['nid_back_side']) ? upload_asset('nid/' . $user->meta['nid_back_side']) : '',
                ];
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'LOGIN_EXCEPTION',
            ]);

            return response()->json(['success' => false, 'message' => __('Internal server error!')], $this->success);
        }

        return response()->json(['success' => true, 'message' => __('Login success'), 'token' => $token, 'user' => $userData], $this->success);
    }

    public function resendCode(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot re-send code')];
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:user_otps,mobile',
        ]);

        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $code = $this->getOtpCode();
                $otps = UserOtp::where(['mobile' => $request->mobile])->first();
                if (strtotime($otps->updated_at) > time() - 900) {
                    $otps->otp_code = $code;
                    $otps->updated_at = now();
                    $otps->attempts = 1;
                    $otps->save();
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $otps->otp_code,
                    ]);
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
                        'message' => 'Your otp code is ' . $otps->otp_code,
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
                    'platform' => $request->platform,
                ],
                [
                    'token' => $request->token,
                ]
            );
            $data['status'] = true;
            $data['message'] = 'Success';
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'AUTH_PUSH_BIND_EXCEPTION',
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    public function forgot(Request $request)
    {
        $data = ['success' => false, 'message' => __('User account not found')];
        try {
            $validator = Validator::make($request->all(), [
                'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:customers,mobile',
            ]);

            if ($validator->fails()) {
                $data['message'] = $validator->errors()->first();
            } else {
                $code = $this->getOtpCode();
                $otp = UserOtp::firstOrNew(['mobile' => $request->mobile, 'type' => 'forgot']);
                $otp->mobile = $request->mobile;
                $otp->otp_code = $code;
                if ($otp->save()) {
                    sendSMS([
                        'mobile' => $request->mobile,
                        'message' => 'Your otp code is ' . $code,
                    ]);
                    $data['success'] = true;
                    $data['step'] = 'forgot_otp';
                    $data['message'] = __('An otp code has been sent to mobile.');
                }
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'FORGOT_PASSWORD_EXCEPTION',
            ]);
            $data['message'] = __('Internal server error!');
        }

        return response()->json($data, $this->success);
    }

    public function reset(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot verify user')];

        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|exists:customers,mobile',
            'password' => 'bail|nullable|min:8|max:20',
            'confirm_password' => 'bail|required|min:8|max:20|same:password',
        ]);

        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $user = Customer::where('mobile', $request->mobile)->first();
                $user->password = Hash::make($request->password);
                $user->save();
                DB::commit();

                Auth::guard('customer')->setUser($user);
                $data['token'] = $user->createToken(config('app.name'))->plainTextToken;

                $data['user'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'type' => 'customer',
                    'photo' => $user->profile_pic ? upload_asset($user->profile_pic) : asset('default/avatar.png'),
                    'role' => 'customer',
                ];

                $data['success'] = true;
                $data['message'] = __('Your password has been reset.');
            } catch (Exception $e) {
                DB::rollback();
                $data['content'] = __('Your password cannot be changed.');
            }
        }

        return response()->json($data, $this->success);
    }

    public function logout(Request $request)
    {
        $user = $request->user('customer') ?? $request->user() ?? Auth::user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } elseif ($user && method_exists($user, 'token') && $user->token()) {
            $user->token()->revoke();
        }

        return response()->json(['success' => true, 'message' => __('You are successfully logout')], $this->success);
    }
}
