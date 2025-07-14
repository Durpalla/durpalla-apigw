<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentCollectorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_collectors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('booking_id')->index();
            $table->bigInteger('payment_id')->index();
            $table->bigInteger('supervisor_id')->index();
            $table->double('amount', [10,2])->default(0);
            $table->text('remarks')->nullable();
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
        Schema::dropIfExists('payment_collectors');
    }
}
