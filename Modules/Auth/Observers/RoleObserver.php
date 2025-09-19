<?php

namespace Modules\Auth\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\Auth\Entities\Role;

class RoleObserver
{
    public function __construct(Role $role)
    {
        Cache::forget('spatie.permission.cache');
    }

    public function created(Role $role): void
    {
        Cache::forget('spatie.permission.cache');
    }

    public function updated(Role $role): void
    {
        Cache::forget('spatie.permission.cache');
    }
}
