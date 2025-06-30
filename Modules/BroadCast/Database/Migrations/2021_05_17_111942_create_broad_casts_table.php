<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBroadCastsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('broad_casts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('title', 191);
            $table->enum('type', ['all', 'sms', 'message', 'email', 'fcm', 'notification', 'topic'])->default('all')->index();
            $table->enum('group', ['all', 'individual'])->default('all')->index();
            $table->text('customers')->nullable();
            $table->text('message');
            $table->string('topic')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('scheduled_at')->nullable();
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
        Schema::dropIfExists('broad_casts');
    }
}
