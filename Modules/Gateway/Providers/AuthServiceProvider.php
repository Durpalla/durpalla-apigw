<?php

namespace Modules\Gateway\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Modules\Gateway\App\Policies\GatewayPolicy;
use Modules\Gateway\Entities\Gateway;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Gateway::class => GatewayPolicy::class,
    ];

    public function boot(): void
    {
    }
}
