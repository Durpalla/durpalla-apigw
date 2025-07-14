<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleRouteMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_route_mappings', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('vehicle_id')->index();
            $table->bigInteger('merchant_id')->index();
            $table->bigInteger('route_id')->index();
            $table->bigInteger('assigned_by');
            $table->tinyInteger('status')->default(1)->index();
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
        Schema::dropIfExists('vehicle_route_mappings');
    }
}
