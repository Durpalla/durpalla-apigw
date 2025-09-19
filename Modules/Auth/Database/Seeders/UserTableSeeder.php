<?php

namespace Modules\Auth\Database\Seeders;

use App\Constants\AppConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Modules\Auth\Entities\Role;
use Modules\Auth\Entities\User;

class UserTableSeeder extends Seeder
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
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
            ],
            [
                'password' => bcrypt('123456789'),
                'email_verified_at' => now(),
                'status' => AppConstant::USER_ACTIVE
            ]
        );
        $user->assignRole(Role::first());
    }
}
