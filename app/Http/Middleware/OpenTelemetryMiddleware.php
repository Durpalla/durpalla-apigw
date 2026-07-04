<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;

class OpenTelemetryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('opentelemetry.tracer')) {
            return $next($request);
        }

        $tracer = app('opentelemetry.tracer');
        $span = $tracer->spanBuilder($request->method().' '.$request->path())
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $next($request);
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setAttribute('http.route', $request->route()?->getName() ?? $request->path());

            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }

            $this->recordRequestMetric($response->getStatusCode());

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $this->recordRequestMetric(500);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function recordRequestMetric(int $statusCode): void
    {
        if (! app()->bound('opentelemetry.meter')) {
            return;
        }

        static $counter = null;

        if ($counter === null) {
            $counter = app('opentelemetry.meter')
                ->createCounter('http.server.requests', 'requests', 'Incoming HTTP requests');
        }

        $counter->add(1, [
            'http.status_code' => $statusCode,
            'service' => config('opentelemetry.service_name'),
        ]);
    }
}
