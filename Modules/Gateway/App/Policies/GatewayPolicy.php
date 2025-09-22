<?php

namespace Modules\Gateway\App\Policies;

use App\Helpers\CommonHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class GatewayPolicy
{
    use HandlesAuthorization;

    public function index(): bool
    {
        return CommonHelper::hasPermission(['gateway-list']);
    }

    public function create(): bool
    {
        return CommonHelper::hasPermission(['gateway-create']);
    }

    public function store(): bool
    {
        return CommonHelper::hasPermission(['gateway-create']);
    }

    public function show(): bool
    {
        return CommonHelper::hasPermission(['gateway-show']);
    }

    public function edit(): bool
    {
        return CommonHelper::hasPermission(['gateway-update']);
    }

    public function update(): bool
    {
        return CommonHelper::hasPermission(['gateway-update']);
    }
}
