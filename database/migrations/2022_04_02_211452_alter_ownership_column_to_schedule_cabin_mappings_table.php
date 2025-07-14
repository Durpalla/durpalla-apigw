<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterOwnershipColumnToScheduleCabinMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            $table->dropColumn('ownership');
            if(!Schema::hasColumn('schedule_cabin_mappings', 'ownership')) {
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
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            $table->string('ownership')->default('jolzan');
        });
    }
}
