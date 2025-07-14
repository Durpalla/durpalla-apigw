<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentTypeColumnToPaymentCollectorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_collectors', function (Blueprint $table) {
            $table->enum('payment_type', ['cash', 'bkash', 'rocket', 'nagad'])->default('cash')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_collectors', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
}
