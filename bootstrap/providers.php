<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    class_exists(App\Providers\OpenTelemetryServiceProvider::class)
        ? App\Providers\OpenTelemetryServiceProvider::class
        : null,
    \App\Providers\PaymentServiceProvider::class,
    \App\Providers\RepositoryServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\ResponseServiceProvider::class,
]));
