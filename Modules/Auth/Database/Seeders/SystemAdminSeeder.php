<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\Role;
use Modules\Auth\Entities\User;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $user = User::updateOrCreate(
            [
                'name' => 'System Admin',
                'email' => 'system@admin.com',
            ],
            [
                'password' => bcrypt(Str::random(18)),
                'email_verified_at' => now(),
                'status' => AuthConstant::USER_ACTIVE,
                'is_editable' => AuthConstant::USER_NOT_EDITABLE
            ]
        );
        $adminRole = Role::where(['guard_name' => 'web', 'name' => 'admin'])->first();
        $user->assignRole($adminRole);
    }
}
