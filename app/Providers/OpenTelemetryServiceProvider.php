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
    public function register(): void
    {
        if (! config('opentelemetry.enabled')) {
            return;
        }

        $this->app->singleton('opentelemetry.tracer', function () {
            $resource = $this->resource();
            $transport = (new OtlpHttpTransportFactory())->create(
                config('opentelemetry.collector_endpoint'),
                'application/x-protobuf'
            );

            $exporter = new SpanExporter($transport);
            $provider = TracerProvider::builder()
                ->addSpanProcessor(new BatchSpanProcessor($exporter))
                ->setResource($resource)
                ->build();

            return $provider->getTracer(config('opentelemetry.service_name'));
        });

        $this->app->singleton('opentelemetry.meter', function () {
            $resource = $this->resource();
            $transport = (new OtlpHttpTransportFactory())->create(
                config('opentelemetry.collector_endpoint'),
                'application/x-protobuf'
            );

            $reader = new ExportingReader(new MetricExporter($transport));
            $provider = MeterProvider::builder()
                ->setResource($resource)
                ->addReader($reader)
                ->build();

            return $provider->getMeter(config('opentelemetry.service_name'));
        });
    }

    public function boot(): void
    {
        if (! config('opentelemetry.enabled') || ! $this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(OpenTelemetryMiddleware::class);
    }

    private function resource(): ResourceInfo
    {
        return ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('opentelemetry.service_name'),
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => config('app.env'),
            ResourceAttributes::SERVICE_VERSION => (string) config('app.version', '1.0.0'),
        ])));
    }
}
