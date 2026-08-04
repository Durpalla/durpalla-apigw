<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agents')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'max_cabin_booking')) {
                $table->unsignedTinyInteger('max_cabin_booking')->nullable()->after('status');
            }
            if (! Schema::hasColumn('agents', 'max_seat_booking')) {
                $table->unsignedTinyInteger('max_seat_booking')->nullable()->after('max_cabin_booking');
            }
            if (! Schema::hasColumn('agents', 'max_deck_booking')) {
                $table->unsignedTinyInteger('max_deck_booking')->nullable()->after('max_seat_booking');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('agents')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            foreach (['max_cabin_booking', 'max_seat_booking', 'max_deck_booking'] as $column) {
                if (Schema::hasColumn('agents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
