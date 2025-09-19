<?php

namespace Modules\Auth\Services;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\Otp;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\LoginVerifyRequest;
use Modules\Auth\Notifications\OtpNotification;
use Modules\Transaction\Entities\Transaction;
use Modules\Transaction\Services\TransactionService;
use Modules\Vendor\Entities\Vendor;

class VendorAuthService
{
    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request)
    {
        try {
            $vendor = Vendor::where('email', $request->input('email'))->first();

            if (!Hash::check($request->input('password'), $vendor->password)) {
                throw ValidationException::withMessages(['password' => __('Password does not match')]);
            }

            $otp = CommonHelper::createOtp(['email' => $request->input('email'), 'type' => AuthConstant::RESELLER_LOGIN_OTP_TYPE]);

            $vendor->notify(new OtpNotification($otp));

            return response()->success([
                'reference' => $otp->reference
            ], __('An otp send to your email. please verify otp.'));
        } catch (ValidationException $exception) {
            // Re-throw ValidationException to be handled by the renderable method
            throw $exception;
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'VENDOR_LOGIN_EXCEPTION'
            ]);
            return response()->error(['message' => $exception->getMessage()]);
        }
    }

    public function verify(LoginVerifyRequest $request)
    {
        try {
            $otp = Otp::where('updated_at', '>', now()->subMinutes(5))
                ->where('type', AuthConstant::RESELLER_LOGIN_OTP_TYPE)
                ->where('reference', $request->input('reference'))
                ->where('revoked', false)
                ->first();

            if (!$otp || $otp->code != $request->input('otp')) {
                throw ValidationException::withMessages(['otp' => __('OTP does not match')]);
            }

            $vendor = Vendor::where('email', $otp->email)->first();

            $otp->revoked();

            return response()->success($vendor->format() +
                [
                    'token' => $vendor->createToken(AuthConstant::TOKEN_NAME)->accessToken,
                    'account' => $vendor->account->balance ?? null
                ]
            );
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'VENDOR_LOGIN_VERIFY_EXCEPTION'
            ]);
            return response()->error(['message' => $exception->getMessage()]);
        }
    }

    public function logout($request)
    {
        try {
            $request->user()->token()->revoke();
            return response()->success();
        } catch (\Exception $exception) {
            return response()->error(['message' => $exception->getMessage()]);
        }
    }

    public function resendOtp($reference)
    {
        try {
            $otp = CommonHelper::createOtp(['reference' => $reference]);
            return response()->success([
                'reference' => $otp->reference
            ]);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'RESEND_OTP_EXCEPTION'
            ]);
            return response()->error(['message' => $exception->getMessage()]);
        }
    }

    public function dashboard(Request $request)
    {
        $vendor = auth('api')->user();
        return response()->success(
            $vendor->format() +
            [
                'account' => $vendor->account->balance ?? null,
                'operator' => $vendor->operators->count(),
                'bundles' => $vendor->bundles->count(),
                'transactions' => Transaction::where('vendor_id', $vendor->id)->sum('amount')
            ]
        );
    }
}
