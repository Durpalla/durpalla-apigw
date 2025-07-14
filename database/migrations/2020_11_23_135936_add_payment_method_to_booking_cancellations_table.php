<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentMethodToBookingCancellationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bkash', 'rocket', 'nagad'])->default('cash');
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
            $table->dropColumn('payment_method');
        });
    }
}
