<?php

namespace Modules\Auth\Policies;

use App\Helpers\CommonHelper;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Auth\Entities\User;

class UserPolicy
{
    use HandlesAuthorization;

    public function index(): bool
    {
        return CommonHelper::hasPermission(['administrator-list']);
    }

    public function show(): bool
    {
        return CommonHelper::hasPermission(['administrator-show']);
    }

    public function create(): bool
    {
        return CommonHelper::hasPermission(['administrator-create']);
    }

    public function store(): bool
    {
        return CommonHelper::hasPermission(['administrator-create']);
    }

    public function edit(): bool
    {
        return CommonHelper::hasPermission(['administrator-update']);
    }

    public function update(): bool
    {
        return CommonHelper::hasPermission(['administrator-update']);
    }

    public function updateStatus(): bool
    {
        return CommonHelper::hasPermission(['administrator-update']);
    }

    public function delete(): bool
    {
        return CommonHelper::hasPermission(['administrator-action']);
    }

    public function restore(): bool
    {
        return CommonHelper::hasPermission(['administrator-action']);
    }

    public function forceDelete(): bool
    {
        return CommonHelper::hasPermission(['administrator-action']);
    }
}
