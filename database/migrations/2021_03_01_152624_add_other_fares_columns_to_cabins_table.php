<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtherFaresColumnsToCabinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cabins', function (Blueprint $table) {
            $table->double('child_fare')->default(0);
            $table->double('infant_fare')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cabins', function (Blueprint $table) {
            $table->dropColumn(['child_fare', 'infant_fare']);
        });
    }
}
