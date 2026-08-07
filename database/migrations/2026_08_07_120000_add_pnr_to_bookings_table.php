<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail loudly if bookings is missing — a silent return marks the
        // migration as ran and blocks a real add later.
        if (! Schema::hasTable('bookings')) {
            throw new RuntimeException(
                'bookings table is missing; cannot add pnr column.'
            );
        }

        if (! Schema::hasColumn('bookings', 'pnr')) {
            Schema::table('bookings', function (Blueprint $table) {
                $col = $table->string('pnr', 21)->nullable()->unique();
                if (Schema::hasColumn('bookings', 'id')) {
                    $col->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'pnr')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropUnique(['pnr']);
                $table->dropColumn('pnr');
            });
        }
    }
};
