<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use OpenTelemetry\API\Logs\LogRecord as OtlpLogRecord;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryLogHandler extends AbstractProcessingHandler
{
    private static ?LoggerProvider $provider = null;

    public function __construct(int|string $level = \Monolog\Level::Debug)
    {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        if (! config('opentelemetry.enabled')) {
            return;
        }

        $logger = $this->provider()->getLogger(config('opentelemetry.service_name'));

        $logRecord = (new OtlpLogRecord($record->message))
            ->setSeverityNumber($this->severityNumber($record->level))
            ->setSeverityText($record->level->getName())
            ->setAttributes(array_merge($record->context, ['channel' => $record->channel]));

        $logger->emit($logRecord);
    }

    private function provider(): LoggerProvider
    {
        if (self::$provider !== null) {
            return self::$provider;
        }

        $transport = (new OtlpHttpTransportFactory())->create(
            config('opentelemetry.collector_endpoint'),
            'application/x-protobuf'
        );

        $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('opentelemetry.service_name'),
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => config('app.env'),
        ])));

        self::$provider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor(new BatchLogRecordProcessor(new LogsExporter($transport)))
            ->build();

        return self::$provider;
    }

    private function severityNumber(\Monolog\Level $level): int
    {
        return match ($level) {
            \Monolog\Level::Debug => 5,
            \Monolog\Level::Info => 9,
            \Monolog\Level::Notice => 10,
            \Monolog\Level::Warning => 13,
            \Monolog\Level::Error => 17,
            \Monolog\Level::Critical => 18,
            \Monolog\Level::Alert => 19,
            \Monolog\Level::Emergency => 21,
        };
    }
}
