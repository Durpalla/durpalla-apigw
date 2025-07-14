<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoutePropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('route_properties', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('route_id')->index();
            $table->bigInteger('ghat_id')->index();
            $table->string('name');
            $table->bigInteger('user_id');
            $table->enum('type', ['start', 'end', 'via'])->default('via');
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
        Schema::dropIfExists('route_properties');
    }
}
