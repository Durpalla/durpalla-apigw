<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class MerchantTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@gmail.com',
            'mobile' => '01776273545',
            'password' => Hash::make('123456789'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'type' => 'merchant'
        ]);
        $role = Role::where('name', 'merchant')->first();
        $user->assignRole($role);

        Merchant::create([
            'user_id' => $user->id,
            'merchant_name' => 'XYZ Merchant Company Ltd.',
            'merchant_reg_no' => '545645646',
            'merchant_reg_expiry_date' => date('Y-m-d'),
            'merchant_email' => $user->email,
            'merchant_mobile' => $user->mobile,
            'created_by' => 1
        ]);
    }
}
