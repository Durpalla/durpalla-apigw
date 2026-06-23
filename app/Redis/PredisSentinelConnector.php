<?php

namespace App\Redis;

use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connectors\PredisConnector;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Arr;
use Predis\Client;

/**
 * Predis + Redis Sentinel for Laravel.
 *
 * Laravel's default PredisConnector expects host/port. Sentinel requires
 * tcp://sentinel:26379 endpoints and replication=sentinel in client options.
 */
class PredisSentinelConnector implements Connector
{
    public function __construct(
        private readonly PredisConnector $default = new PredisConnector,
    ) {}

    public function connect(array $config, array $options)
    {
        $sentinels = $config['sentinels'] ?? null;

        if (! is_array($sentinels) || $sentinels === []) {
            return $this->default->connect($config, $options);
        }

        $endpoints = array_map(
            static fn (array $sentinel): string => sprintf(
                'tcp://%s:%d',
                $sentinel['host'],
                $sentinel['port'] ?? 26379,
            ),
            $sentinels,
        );

        $password = Arr::get($options, 'parameters.password');
        if ($password === 'null' || $password === '') {
            $password = null;
        }

        $timeout = (float) env('REDIS_TIMEOUT', 2.0);

        $formattedOptions = array_merge(
            [
                'timeout' => $timeout,
                'read_write_timeout' => $timeout,
            ],
            Arr::except($options, ['replication', 'cluster']),
            [
                'replication' => 'sentinel',
                'service' => $config['service'] ?? 'mymaster',
                'parameters' => array_filter([
                    'password' => $password,
                    'database' => $config['database'] ?? 0,
                ], static fn ($value) => $value !== null && $value !== ''),
            ],
        );

        if (isset($config['prefix'])) {
            $formattedOptions['prefix'] = $config['prefix'];
        }

        return new PredisConnection(new Client($endpoints, $formattedOptions));
    }

    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        return $this->default->connectToCluster($config, $clusterOptions, $options);
    }
}
