<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
    */
    public function up()
    {
        Schema::create('coupon_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('coupon_id')->index();
            $table->bigInteger('item_id')->index();
            $table->enum('type', ['merchant', 'route', 'launch', 'customer', 'period'])->index();
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
        Schema::dropIfExists('coupon_mappings');
    }
}
