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
        Schema::table('payments', function (Blueprint $table) {
            if(!Schema::hasColumn('payments', 'gateway_id')) {
                $table->foreignId('gateway_id')->nullable()->constrained('gateways');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if(Schema::hasColumn('payments', 'gateway_id')) {
                $table->dropForeign('payments_gateway_id_foreign');
                $table->dropColumn('gateway_id');
            }
        });
    }
};
