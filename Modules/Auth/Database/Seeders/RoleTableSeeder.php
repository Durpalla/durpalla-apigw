<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleTableSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'provider'],
            ['name' => 'admin', 'guard_name' => 'vendor']
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate($role, $role + ['is_editable' => false]);
        }
    }
}
