<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'hotel_reviews_hotel_id_user_id_unique';

    public function up(): void
    {
        $table = 'hotel_reviews';
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'user_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('user_id')->nullable()->after('hotel_id');
            });
        }

        if (! Schema::hasIndex($table, self::INDEX_NAME)) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique(['hotel_id', 'user_id'], self::INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        $table = 'hotel_reviews';
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasIndex($table, self::INDEX_NAME)) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(self::INDEX_NAME);
            });
        }

        if (Schema::hasColumn($table, 'user_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('user_id');
            });
        }
    }
};
