<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehiclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('route_id')->index();
            $table->bigInteger('merchant_id')->index();
            $table->bigInteger('user_id')->index();
            $table->string('name');
            $table->integer('vehicle_no')->nullable();
            $table->string('registration_no');
            $table->string('engine_no')->nullable();
            $table->date('registration_expiry_date')->nullable();
            $table->date('fitness_expiry_date')->nullable();
            $table->integer('passengers_capacity');
            $table->string('photo')->nullable();
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
        Schema::dropIfExists('vehicles');
    }
}
