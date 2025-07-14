<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RescheduleCancleVehicleSchedule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->index();
            $table->bigInteger('vehicle_schedule_id')->nullable();
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
            $table->dropColumn('status');
            $table->dropColumn('vehicle_schedule_id');
        });
    }
}
