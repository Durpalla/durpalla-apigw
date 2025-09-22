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
        Schema::table('medias', function (Blueprint $table) {
            if(!Schema::hasColumn('medias', 'is_cloud')) {
                $table->boolean('is_cloud')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table) {
            if(Schema::hasColumn('medias', 'is_cloud')) {
                $table->dropColumn('is_cloud');
            }
        });
    }
};
