<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('merchant_id');
            $table->bigInteger('vehicle_id');
            $table->bigInteger('schedule_id');
            $table->string('description')->nullable();
            $table->double('amount', [10,2])->default(0);
            $table->enum('type', ['p', 'f'])->default('p')->comment('p = percent, f= fixed');
            $table->tinyInteger('is_cabin')->default(0);
            $table->tinyInteger('is_seat')->default(0);
            $table->tinyInteger('is_deck')->default(0);
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
        Schema::dropIfExists('discounts');
    }
}
