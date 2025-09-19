<?php

namespace Modules\Auth\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\Auth\Entities\Permission;

class PermissionObserver
{
    public function __construct(Permission $permission)
    {
        Cache::forget('permissions');
    }
}
