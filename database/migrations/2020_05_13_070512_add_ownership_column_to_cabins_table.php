<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOwnershipColumnToCabinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cabins', function (Blueprint $table) {
            if(!Schema::hasColumn('cabins', 'ownership')) {
                $table->string('ownership')->default('jolzan');
            }
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
            if(Schema::hasColumn('cabins', 'ownership')) {
                $table->dropColumn('ownership');
            }
        });
    }
}
