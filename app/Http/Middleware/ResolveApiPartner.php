<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the API-partner Party that owns the authenticated OAuth client
 * and binds it into the request for downstream reseller controllers.
 *
 * Must run after the `client` middleware (EnsureClientIsResourceOwner).
 */
class ResolveApiPartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = auth('api')->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing client credentials.',
            ], 401);
        }

        $owner = $client->owner;

        if (! $owner instanceof Party || ! $owner->isApiPartner() || (int) ($owner->status ?? 0) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'This client is not linked to an active API partner.',
            ], 403);
        }

        $request->attributes->set('api_partner', $owner);
        app()->instance('api_partner', $owner);

        return $next($request);
    }
}
