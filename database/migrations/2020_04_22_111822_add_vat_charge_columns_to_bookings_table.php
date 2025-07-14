<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVatChargeColumnsToBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->double('vat_amount', [12,2])->default(0);
            $table->double('vat_total', [12,2])->default(0);
            $table->double('charge_amount', [4,2])->default(0);
            $table->double('charge_total', [12,2])->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('vat_amount');
            $table->dropColumn('vat_total');
            $table->dropColumn('charge_amount');
            $table->dropColumn('charge_total');
        });
    }
}
