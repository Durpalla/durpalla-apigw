<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreColumnsToBookingCancellationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->tinyInteger('vat_refundable')->default(1);
            $table->tinyInteger('charge_refundable')->default(1);
            $table->double('total_refundable', [12,2])->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->dropColumn('vat_refundable');
            $table->dropColumn('charge_refundable');
            $table->dropColumn('total_refundable');
        });
    }
}
