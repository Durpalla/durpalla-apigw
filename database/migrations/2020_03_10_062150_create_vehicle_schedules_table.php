<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_schedules', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('route_id')->index();
            $table->bigInteger('vehicle_id')->index();
            $table->bigInteger('merchant_id')->index();
            $table->date('schedule_date')->index();
            $table->enum('schedule_type', ['straight', 'reverse'])->default('straight');
            $table->string('starting_point')->nullable();
            $table->datetime('leaving_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_schedules');
    }
}
