<?php

namespace Modules\Auth\Entities;

use Modules\Activity\App\Traits\ActivityTrait;

class Permission extends \Spatie\Permission\Models\Permission
{
    use ActivityTrait;
    protected $fillable = ['name', 'guard_name'];
    protected $logAttributes = ['name', 'guard_name'];
    protected $logOnlyDirty = true;
    protected $guard_name = 'web';

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Permission {$eventName}";
    }


}
