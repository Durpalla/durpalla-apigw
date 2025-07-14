<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingCancellationItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_cancellation_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('booking_cancellation_id');
            $table->bigInteger('booking_item_id');
            $table->bigInteger('officer_id');
            $table->bigInteger('customer_id');
            $table->tinyInteger('status')->default(0);
            $table->double('refundable_amount')->default(0);
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
        Schema::dropIfExists('booking_cancellation_items');
    }
}
