<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Traits\LoginTrait;

class AuthChecker
{
    use LoginTrait;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if(auth()->check()) {
            $user = auth()->user();
            if($user->status != AuthConstant::USER_ACTIVE) {
                return $this->sessionExpire(__('Your account is not active'));
            }

            if($user->isBlocked()) {
                return $this->sessionExpire(__('Your account has been blocked.'));
            }
        }
        return $next($request);
    }
}
