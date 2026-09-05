<?php

namespace App\Http\Middleware;

use App\Support\ApiLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = ApiLocale::apply($request);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
