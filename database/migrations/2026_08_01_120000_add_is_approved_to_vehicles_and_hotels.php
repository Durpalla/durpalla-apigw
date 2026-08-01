<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && ! Schema::hasColumn('vehicles', 'is_approved')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->boolean('is_approved')->default(false)->after('status');
            });
            DB::table('vehicles')->update(['is_approved' => true]);
        }

        if (Schema::hasTable('hotels') && ! Schema::hasColumn('hotels', 'is_approved')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->boolean('is_approved')->default(false)->after('status');
            });
            DB::table('hotels')->update(['is_approved' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'is_approved')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('is_approved');
            });
        }

        if (Schema::hasTable('hotels') && Schema::hasColumn('hotels', 'is_approved')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->dropColumn('is_approved');
            });
        }
    }
};
