<?php

use App\Providers\ViewComposerServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    \App\Providers\PaymentServiceProvider::class,
    \App\Providers\RepositoryServiceProvider::class,
    \App\Providers\ViewServiceProvider::class,
    ViewComposerServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class
];
