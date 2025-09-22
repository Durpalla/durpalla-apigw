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
            if(!Schema::hasColumn('medias', 'ratio')) {
                $table->string('ratio')->after('dimension')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table) {
            if(Schema::hasColumn('medias', 'ratio')) {
                $table->dropColumn('ratio');
            }
        });
    }
};
