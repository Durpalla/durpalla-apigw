<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if(!Schema::hasColumns('cart_items', ['payload', 'is_locked', 'locked_id'])) {
                $table->json('payload')->nullable();
                $table->boolean('is_locked')->default(true);
                $table->string('locked_id')->nullable();
            };
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if(Schema::hasColumns('cart_items', ['payload', 'is_locked', 'locked_id'])) {
                $table->dropColumn(['payload', 'is_locked', 'locked_id']);
            }
        });
    }
};
