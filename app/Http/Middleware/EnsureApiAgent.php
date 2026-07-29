<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent access required.',
            ], 403);
        }

        return $next($request);
    }
}
