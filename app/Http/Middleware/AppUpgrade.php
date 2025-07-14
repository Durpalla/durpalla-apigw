<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AppUpgrade
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle($request, Closure $next): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Version v1 not uses, please upgrade your app.'], 403);
    }
}
