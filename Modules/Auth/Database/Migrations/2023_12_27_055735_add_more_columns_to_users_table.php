<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMoreColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if(!Schema::hasColumns('users', ['status', 'is_editable'])) {
                $table->tinyInteger('status')->default(0)->index();
                $table->boolean('is_editable')->default(true)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if(Schema::hasColumns('users', ['status', 'is_editable'])) {
                $table->dropColumn(['status', 'is_editable']);
            }
        });
    }
}
