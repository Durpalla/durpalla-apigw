<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_access_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type')->default('LOGIN_ATTEMPT')->index();
            $table->string('identity')->nullable()->index();
            $table->integer('attempts')->default(1);
            $table->dateTime('blocked_at')->nullable();
            $table->dateTime('unblocked_at')->nullable()->index();
            $table->boolean('is_blocked')->default(false)->index();
            $table->json('audit')->nullable();
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('unblocked_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['created_at', 'blocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_access_blocks');
    }
};
