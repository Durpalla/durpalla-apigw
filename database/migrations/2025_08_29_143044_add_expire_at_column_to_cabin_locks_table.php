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
        Schema::table('cabin_locks', function (Blueprint $table) {
            if(!Schema::hasColumn('cabin_locks', 'expire_at')) {
                $table->timestamp('expire_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cabin_locks', function (Blueprint $table) {
            if(Schema::hasColumn('cabin_locks', 'expire_at')) {
                $table->dropColumn('expire_at');
            }
        });
    }
};
