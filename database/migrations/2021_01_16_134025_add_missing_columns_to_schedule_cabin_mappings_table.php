<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToScheduleCabinMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            $table->bigInteger('vehicle_id');
            $table->bigInteger('merchant_id');
            $table->bigInteger('type_id');
            $table->tinyInteger('floor')->default(2);
            $table->integer('cabin_position')->default(99);
            $table->integer('cabin_row')->default(1);
            $table->integer('passenger_capacity')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            $table->dropColumn(['vehicle_id', 'merchant_id', 'type_id', 'floor', 'cabin_position', 'cabin_row', 'passenger_capacity']);
        });
    }
}
