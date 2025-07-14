<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->unique();
            $table->string('session_key', 191)->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('account_no')->nullable();
            $table->double('store_amount', [12,2])->default(0);
            $table->enum('currency', ['BDT', 'USD'])->default('BDT');
            $table->enum('status', ['pending', 'canceled', 'fail', 'success', 'verified'])->default('pending')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
            $table->dropColumn('session_key');
            $table->dropColumn('payment_method');
            $table->dropColumn('payment_gateway');
            $table->dropColumn('status');
        });
    }
}
