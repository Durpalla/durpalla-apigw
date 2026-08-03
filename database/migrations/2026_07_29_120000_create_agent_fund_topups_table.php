<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_fund_topups')) {
            return;
        }

        Schema::create('agent_fund_topups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['gateway', 'bank_transfer'])->default('gateway');
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->string('transaction_ref')->nullable()->index();
            $table->string('gateway_trx_id')->nullable();
            $table->enum('status', ['pending_payment', 'pending_admin', 'success', 'failed', 'cancelled'])
                ->default('pending_payment');
            $table->text('payment_url')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('agents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_fund_topups');
    }
};
