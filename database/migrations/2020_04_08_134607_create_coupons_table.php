<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
    */
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('user_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code', 12)->index();
            $table->enum('discount_type', ['percent', 'flat'])->default('percent');
            $table->double('discount_amount', [10,2])->default(0);
            $table->enum('type', ['merchant', 'route', 'launch', 'customer', 'period'])->default('period')->index();
            $table->date('offer_start')->index();
            $table->date('offer_end')->index();
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
        Schema::dropIfExists('coupons');
    }
}
