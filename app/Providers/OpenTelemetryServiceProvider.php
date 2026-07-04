<?php

namespace App\Providers;

use App\Http\Middleware\OpenTelemetryMiddleware;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    private ?TracerProvider $tracerProvider = null;

    private ?MeterProvider $meterProvider = null;

    public function register(): void
    {
        if (! config('opentelemetry.enabled')) {
            return;
        }

        $this->app->singleton('opentelemetry.tracer', function () {
            $transport = (new OtlpHttpTransportFactory())->create(
                config('opentelemetry.collector_endpoint').'/v1/traces',
                'application/x-protobuf'
            );

            $this->tracerProvider = TracerProvider::builder()
                ->addSpanProcessor(BatchSpanProcessor::builder(new SpanExporter($transport))->build())
                ->setResource($this->resource())
                ->build();

            return $this->tracerProvider->getTracer(config('opentelemetry.service_name'));
        });

        $this->app->singleton('opentelemetry.meter', function () {
            $transport = (new OtlpHttpTransportFactory())->create(
                config('opentelemetry.collector_endpoint').'/v1/metrics',
                'application/x-protobuf'
            );

            $reader = new ExportingReader(new MetricExporter($transport));
            $this->meterProvider = MeterProvider::builder()
                ->setResource($this->resource())
                ->addReader($reader)
                ->build();

            return $this->meterProvider->getMeter(config('opentelemetry.service_name'));
        });
    }

    public function boot(): void
    {
        if (! config('opentelemetry.enabled') || ! $this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(OpenTelemetryMiddleware::class);

        // Batch processors buffer spans/metrics in memory; force export before the
        // PHP-FPM worker or console command exits so nothing is dropped.
        $this->app->terminating(function () {
            $this->tracerProvider?->forceFlush();
            $this->meterProvider?->forceFlush();
        });
    }

    private function resource(): ResourceInfo
    {
        return ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('opentelemetry.service_name'),
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => config('app.env'),
            ResourceAttributes::SERVICE_VERSION => (string) config('app.version', '1.0.0'),
        ])));
    }
}
