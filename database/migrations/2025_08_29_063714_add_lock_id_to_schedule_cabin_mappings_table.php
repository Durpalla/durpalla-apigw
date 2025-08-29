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
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            if(!Schema::hasColumn('schedule_cabin_mappings', 'lock_id')) {
                $table->foreignId('lock_id')->nullable()->constrained('schedule_cabin_mappings', 'id')->onDelete('set null')->onUpdate('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_cabin_mappings', function (Blueprint $table) {
            if(Schema::hasColumn('schedule_cabin_mappings', 'lock_id')) {
                $table->dropForeign('schedule_cabin_mappings_lock_id_foreign');
                $table->dropColumn('lock_id');
            }
        });
    }
};
