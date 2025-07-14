<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOperationHourColumnToVehicleSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->dateTime('operation_timeline')->default(now());
            $table->double('operation_hour', [2,2])->default(8);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->dropColumn(['operation_timeline', 'operation_hour']);
        });
    }
}
