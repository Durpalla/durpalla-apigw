<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
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
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'mobile' => '01911785317',
            'password' => Hash::make('123456789'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'type' => 'admin'
        ]);

        $role = Role::where('name', 'admin')->first();
        $user->assignRole($role);

        //create 1000 users with just one line by Factory Model
        // $userRole = Role::where('name', 'customer')->first();
        // factory(User::class, 10)->create()->each( function( $u ) use ($userRole) {
        //     $u->assignRole($userRole);
        // });
    }
}
