<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeckFaresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
    */
    public function up()
    {
        Schema::create('deck_fares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('route_id')->index();
            $table->bigInteger('merchant_id')->index();
            $table->bigInteger('vehicle_id')->index();
            $table->bigInteger('departure_from')->index();
            $table->bigInteger('departure_to')->index();
            $table->double('fare', [10,2])->default(0.00);
            $table->enum('type', ['straight', 'reverse'])->default('straight')->index();
            $table->bigInteger('user_id')->index();
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
        Schema::dropIfExists('deck_fares');
    }
}
