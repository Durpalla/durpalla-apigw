<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduleCabinMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schedule_cabin_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cabin_id')->index();
            $table->bigInteger('schedule_id')->index();
            $table->enum('type', ['seat', 'cabin', 'sofa'])->default('cabin');
            $table->double('fare', [10,2])->default(0);
            $table->enum('ownership', ['jolzatra', 'merchant', 'other', 'both']);
            $table->tinyInteger('honorium')->default(0)->comment('1 = yes, o = no');
            $table->tinyInteger('booked')->default(0)->comment('0 = available, 1 = booked');
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
        Schema::dropIfExists('schedule_cabin_mappings');
    }
}
