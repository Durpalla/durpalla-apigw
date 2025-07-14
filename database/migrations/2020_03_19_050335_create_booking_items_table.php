<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBookingItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('booking_id')->index();
            $table->bigInteger('customer_id')->index();
            $table->bigInteger('vehicle_id')->index();
            $table->bigInteger('cabin_id')->nullable()->index();
            $table->string('booking_type')->default('deck');
            $table->double('price', [10,2])->default(0.00);
            $table->softDeletes();
        });
//        DB::update("ALTER TABLE booking_items AUTO_INCREMENT = 1000;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('booking_items');
    }
}
