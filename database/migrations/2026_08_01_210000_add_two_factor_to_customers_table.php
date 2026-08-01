<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('email_verified_at');
            }
            if (! Schema::hasColumn('customers', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'two_factor_confirmed_at')) {
                $table->dropColumn('two_factor_confirmed_at');
            }
            if (Schema::hasColumn('customers', 'two_factor_enabled')) {
                $table->dropColumn('two_factor_enabled');
            }
        });
    }
};
