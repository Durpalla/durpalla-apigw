<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hotel_reviews')) {
            return;
        }

        Schema::create('hotel_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_id');
            $table->string('author', 191);
            $table->decimal('rating', 3, 1);
            $table->text('body');
            $table->timestamp('reviewed_at')->nullable();
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_reviews');
    }
};
