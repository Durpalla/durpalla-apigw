<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreColumnsToScheduleCabinMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            $table->string('cabin_no')->nullable();
            $table->double('service_charge')->default(0);
            $table->tinyInteger('is_locked')->default(0);
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
            $table->dropColumn(['cabin_no', 'service_charge', 'is_locked']);
        });
    }
}
