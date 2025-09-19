<?php

namespace Modules\Auth\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Constants\AuthConstant;

trait LoginTrait
{
    public function redirectTo(): string
    {
        return AuthConstant::DASHBOARD;
    }

    public function is2FaEnabled(): string
    {
        return config('auth.2fa_enabled', true);
    }

    public static function sessionExpire($message = 'Your session expired!'): RedirectResponse
    {
        if(Auth::check()) {
            Auth::logout();
            session()->flush();
        }
        return redirect()->route('auth.login')->with(['message' => ['label' => 'info', 'content' => $message]]);
    }
}
