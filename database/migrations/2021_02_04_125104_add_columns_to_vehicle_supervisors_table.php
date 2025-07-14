<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToVehicleSupervisorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_supervisors', function (Blueprint $table) {
            $table->tinyInteger('is_master')->default(0);
            $table->bigInteger('master_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicle_supervisors', function (Blueprint $table) {
            $table->dropColumn(['is_master', 'master_id']);
        });
    }
}
