<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentWithdrawalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('officer_id')->nullable();
            $table->bigInteger('agent_payment_method_id');
            $table->double('balance');
            $table->double('amount');
            $table->string('transaction_reference')->nullable();
            $table->tinyInteger('status')->comment('0 = pending, 1 = complete, 2 = declined')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_withdrawals');
    }
}
