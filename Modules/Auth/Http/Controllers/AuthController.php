<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery\Exception;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\Otp;
use Modules\Auth\Entities\User;
use Modules\Auth\Http\Requests\AuthLoginRequest;
use Modules\Auth\Http\Requests\ChangePasswordRequest;
use Modules\Auth\Http\Requests\Login2FaRequest;
use Modules\Auth\Http\Requests\ResetEmailRequest;
use Modules\Auth\Http\Requests\ResetPasswordRequest;
use Modules\Auth\Interfaces\LoginInterface;
use Modules\Auth\Traits\Auth2FaTrait;
use Modules\Auth\Traits\LoginTrait;

class AuthController extends Controller implements LoginInterface
{
    use Auth2FaTrait, LoginTrait;

    public function index(): View
    {
        return $this->themedView('auth::index');
    }

    public function token(Request $request)
    {
        return $request->user();
    }

    public function login(): View
    {
        return $this->themedView('auth::auth.login');
    }

    public function check(AuthLoginRequest $request): RedirectResponse
    {
        try {
            if ($this->is2FaEnabled()) {
                $otp = Otp::create(
                    [
                        'type' => AuthConstant::LOGIN_OTP_TYPE,
                        'email' => $request->input('email'),
                        'code' => $this->newOtp()
                    ]
                );

                return redirect()->route('auth.2fa', ['token' => $this->generateToken($otp->email)])
                    ->with(['message' => ['label' => 'success', 'content' => 'Login success! please verify 2fa']]);
            } else {
                return redirect()->back()->withInput($request->all());
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'USER_AUTH_CHECK_EXCEPTION',
            ]);
        }

        return redirect()->route('auth.login');
    }

    public function otp(Request $request): View|RedirectResponse
    {
        $email = $this->decryptToken($request->input('token'));
        $otp = Otp::where('updated_at', '>=', now()->subMinutes(5))
            ->where('type', AuthConstant::LOGIN_OTP_TYPE)
            ->where('email', $email)
            ->first();

        if (!$otp) {
            return redirect()->route('auth.login');
        }

        return $this->themedView('auth::auth.code', [
            'token' => $request->input('token'),
            'email' => $email
        ]);
    }

    public function verify(Login2FaRequest $request): RedirectResponse
    {
        try {
            $user = User::where('email', $this->decryptToken($request->input('token')))->first();
            Auth::guard('web')->login($user, true);
            Otp::where('email', $this->decryptToken($request->input('token')))
                ->where('type', AuthConstant::LOGIN_OTP_TYPE)
                ->update(['revoked' => true]);
            return redirect()->intended($this->redirectTo());
        } catch (\Exception $exception) {
            LogHelper::exception($exception);
        }

        return redirect()->back()->with([
            'message' => [
                'label' => 'success',
                'content' => __('Sorry! could not verify.!')
            ]
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        return redirect()->route('auth.login')->with([
            'message' => [
                'label' => 'success',
                'content' => __('You have been successfully logged out!')
            ]
        ]);
    }

    public function profile(): View
    {
        $user = Auth::user();
        return $this->themedView('auth::user.show', ['user' => $user])->with(['title' => 'My profile']);
    }

    public function changePassword(): View
    {
        $user = Auth::user();
        return $this->themedView('auth::change-password', [
            'user' => $user,
            'title' => 'Change Password'
        ]);

    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->input('password'))
        ]);
        return redirect()->route('auth.profile')->with(['message' => 'Password has been updated!']);
    }

    public function forgotPassword(): View
    {
        return $this->themedView('auth::reset-email')->with(['title' => 'Forgot Password']);
    }

    public function resetOtp(ResetEmailRequest $request): RedirectResponse
    {
        $otp = Otp::create([
            'type' => AuthConstant::RESET_OTP_TYPE,
            'email' => $request->input('email'),
            'code' => $this->newOtp()
        ]);
        return redirect()->route('auth.get-otp', $this->generateToken($request->input('email')));
    }

    public function getotp($token): View|RedirectResponse
    {
        $otp = Otp::where('updated_at', '>=', now()->subMinutes(5))
            ->where('type', AuthConstant::RESET_OTP_TYPE)
            ->where('email', $this->decryptToken($token))
            ->first();
        if (!$otp) {
            return redirect()->route('auth.forgot-password')->with(['message' => 'Sorry! Your OTP code has expired.']);
        }
        return $this->themedView('auth::reset-otp', ['token' => $token])->with(['title' => 'Reset OTP']);
    }

    public function verifyResetOtp(Login2FaRequest $request): RedirectResponse
    {
        try {
            return redirect()->route('auth.get-reset-password', $request->input('token'));

        } catch (\Exception $exception) {
            LogHelper::exception($exception);
            return redirect()->back()->withInput($request->all())->withErrors($exception->getMessage());
        }

    }

    public function getResetPassword($token): View
    {
        return $this->themedView('auth::reset-password', ['token' => $token]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $user = User::where('email', $this->decryptToken($request->input('token')))->first();
            $user->update([
                'password' => $request->input('password')
            ]);
            Otp::where('email', $this->decryptToken($request->input('token')))
                ->where('type', AuthConstant::RESET_OTP_TYPE)
                ->update(['revoked' => true]);

            return redirect()->route('auth.login')->with(['message' => 'Password has been updated!']);
        } catch (Exception $exception) {
            LogHelper::exception($exception);
            return redirect()->back()->withInput($request->all())->withErrors($exception->getMessage());
        }
    }
}
