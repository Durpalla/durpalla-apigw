<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingCancellationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('booking_id')->index();
            $table->bigInteger('customer_id')->index();
            $table->bigInteger('user_id')->nullable();
            $table->enum('type', ['p', 't'])->comment('p = Partial, t = Total')->index();
            $table->string('items');
            $table->tinyInteger('status')->default(0)->index();
            $table->softDeletes();
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
        Schema::dropIfExists('booking_cancellations');
    }
}
