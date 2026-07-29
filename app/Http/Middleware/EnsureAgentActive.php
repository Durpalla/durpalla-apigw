<?php

namespace App\Http\Middleware;

use App\Constants\AppConst;
use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->user();

        if (! $agent instanceof Agent || (int) $agent->status !== AppConst::USER_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your agent account is not active. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
