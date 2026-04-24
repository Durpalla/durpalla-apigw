<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'hotels';
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'review_count')) {
                $blueprint->unsignedInteger('review_count')->default(0);
            }
            if (! Schema::hasColumn($table, 'aggregate_rating')) {
                $blueprint->decimal('aggregate_rating', 4, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        $table = 'hotels';
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (Schema::hasColumn($table, 'aggregate_rating')) {
                $blueprint->dropColumn('aggregate_rating');
            }
            if (Schema::hasColumn($table, 'review_count')) {
                $blueprint->dropColumn('review_count');
            }
        });
    }
};
