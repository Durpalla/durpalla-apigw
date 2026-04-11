<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test DB only. Production uses the same database as the main application (no migrations there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 14)->index();
            $table->string('otp_code');
            $table->string('type')->default('login');
            $table->integer('attempts')->default(0);
            $table->tinyInteger('verified')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
