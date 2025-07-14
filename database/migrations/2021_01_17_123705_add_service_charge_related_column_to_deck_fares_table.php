<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceChargeRelatedColumnToDeckFaresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deck_fares', function (Blueprint $table) {
            $table->double('service_charge')->default(0);
            $table->enum('service_charge_type', ['percent', 'fixed'])->default('percent');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deck_fares', function (Blueprint $table) {
            $table->dropColumn(['service_charge', 'service_charge_type']);
        });
    }
}
