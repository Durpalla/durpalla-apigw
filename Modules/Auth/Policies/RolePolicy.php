<?php

namespace Modules\Auth\Policies;

use App\Helpers\CommonHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function index(): bool
    {
        return CommonHelper::hasPermission(['role-list']);
    }

    public function show(): bool
    {
        return CommonHelper::hasPermission(['role-show']);
    }

    public function create(): bool
    {
        return CommonHelper::hasPermission(['role-create']);
    }

    public function store(): bool
    {
        return CommonHelper::hasPermission(['role-create']);
    }

    public function edit(): bool
    {
        return CommonHelper::hasPermission(['role-update', 'role-edit']);
    }

    public function update(): bool
    {
        return CommonHelper::hasPermission(['role-update', 'role-edit']);
    }

    public function delete(): bool
    {
        return CommonHelper::hasPermission(['role-action']);
    }

    public function restore(): bool
    {
        return CommonHelper::hasPermission(['role-action']);
    }

    public function forceDelete(): bool
    {
        return CommonHelper::hasPermission(['role-action']);
    }
}
