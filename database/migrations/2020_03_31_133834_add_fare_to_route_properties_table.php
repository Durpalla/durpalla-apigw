<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFareToRoutePropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('route_properties', function (Blueprint $table) {
            $table->string('before_fare')->nullable();
            $table->string('after_fare')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('route_properties', function (Blueprint $table) {
            $table->dropColumn(['before_fare']);
            $table->dropColumn(['after_fare']);
        });
    }
}
