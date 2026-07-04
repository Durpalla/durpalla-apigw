<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\OpenTelemetryServiceProvider::class,
    \App\Providers\PaymentServiceProvider::class,
    \App\Providers\RepositoryServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\ResponseServiceProvider::class,
];
