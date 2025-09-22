<?php

namespace Modules\Media\App\Policies;

use App\Helpers\CommonHelper;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    public function index(): bool
    {
        return CommonHelper::hasPermission(['media-list']);
    }

    public function create(): bool
    {
        return CommonHelper::hasPermission(['media-create']);
    }

    public function store(): bool
    {
        return CommonHelper::hasPermission(['media-create']);
    }

    public function show(): bool
    {
        return CommonHelper::hasPermission(['media-show']);
    }

    public function edit(): bool
    {
        return CommonHelper::hasPermission(['media-update']);
    }

    public function update(): bool
    {
        return CommonHelper::hasPermission(['media-update']);
    }
}
