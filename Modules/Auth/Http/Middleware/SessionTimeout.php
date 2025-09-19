<?php

namespace Modules\Auth\Http\Middleware;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Helpers\AuthHelper;

class SessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $timeout = config('auth.session_lifetime') * 60; // Timeout in seconds
            try {
                $lastActivity = session()->get('last_activity_time');

                if($lastActivity) {
                    if (time() - $lastActivity > $timeout) {
                        return AuthHelper::sessionExpire(__('Your session has expired due to inactivity.'));
                    }
                }

                session()->put('last_activity_time', time());
            } catch (\Exception $e) {
                LogHelper::error($e->getMessage(), [
                    'keyword' => 'SESSION_TIMEOUT_MIDDLEWARE_EXCEPTION',
                ]);
            }
        }

        return $next($request);
    }
}
