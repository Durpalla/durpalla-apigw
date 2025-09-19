<?php

namespace Modules\Auth\Helpers;

use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    public static function hasPermission(array $permissions): bool
    {
        return (auth()->check() && auth()->user()->can($permissions)) || auth()->user()->hasRole('admin');
    }

    public static function sessionExpire($message)
    {
        if (Auth::check()) {
            Auth::logout();
            session()->flush();
            return redirect()->route('auth.login')->with(['message' => ['label' => 'info', 'content' => $message]]);
        }
    }
}
