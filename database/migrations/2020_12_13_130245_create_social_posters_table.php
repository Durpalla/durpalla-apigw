<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialPostersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_posters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('vehicle_schedule_id')->index();
            $table->bigInteger('merchant_id')->nullable();
            $table->bigInteger('vehicle_id')->nullable();
            $table->bigInteger('user_id')->unsigned();
            $table->string('route_name');
            $table->string('vehicle_name');
            $table->integer('share_count')->default(0);
            $table->string('poster');
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
        Schema::dropIfExists('social_posters');
    }
}
