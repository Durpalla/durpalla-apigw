<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index();
            $table->string('merchant_name');
            $table->string('merchant_reg_no');
            $table->date('merchant_reg_expiry_date');
            $table->string('merchant_address')->nullable();
            $table->string('merchant_email')->index();
            $table->string('merchant_mobile')->index();
            $table->string('merchant_phone')->nullable();
            $table->string('merchant_fax')->nullable();
            $table->bigInteger('created_by')->index();
            $table->tinyInteger('status')->default(1)->index()->comment('[1=active, 0=pending, 2=deactive]');
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
        Schema::dropIfExists('merchants');
    }
}
