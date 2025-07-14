<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
        	['name' => 'admin', 'type' => 'admin'],
            ['name' => 'officer', 'type' => 'admin'],
            ['name' => 'support', 'type' => 'admin'],
        	['name' => 'customer', 'type' => 'customer'],
        	['name' => 'merchant', 'type' => 'merchant'],
        	['name' => 'supervisor', 'type' => 'merchant'],
        	['name' => 'manager', 'type' => 'merchant'],
            ['name' => 'counter-officer', 'type' => 'merchant']
        ];

        foreach ($roles as $role) {
             Role::create(['name' => $role['name'], 'type' => $role['type'], 'guard_name' => 'web']);
        }
    }
}
