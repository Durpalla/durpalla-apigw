<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCabinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cabins', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('vehicle_id')->index();
            $table->bigInteger('marchant_id')->index();
            $table->tinyInteger('floor');
            $table->string('cabin_no')->index();
            $table->double('fare', [10,2])->index();
            $table->bigInteger('type_id')->index();
            $table->integer('passenger_capacity');
            $table->bigInteger('created_by');
            $table->integer('cabin_row')->default(1);
            $table->integer('cabin_position')->default(99);
            $table->tinyInteger('status')->default(1);
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
        Schema::dropIfExists('cabins');
    }
}
