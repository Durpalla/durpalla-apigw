<?php

namespace Modules\Gateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Gateway\Constants\GatewayConstant;
use Modules\Gateway\Entities\Gateway;

class GatewayTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $gateways = [
            [
                'name' => 'FIB',
                'class_name' => 'App\Gateways\FIB',
                'status' => GatewayConstant::ACTIVE
            ]
        ];

        foreach($gateways as $gateway) {
            Gateway::updateOrCreate([
                'name' => $gateway['name']
            ], $gateway);
        }
    }
}
