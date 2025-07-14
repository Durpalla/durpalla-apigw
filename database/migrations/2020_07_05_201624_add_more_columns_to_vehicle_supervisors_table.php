<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreColumnsToVehicleSupervisorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle_supervisors', function (Blueprint $table) {
            $table->double('supervisor_incentive', [12,2])->default(0);
            $table->enum('incentive_type', ['percent', 'fixed'])->default('percent');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicle_supervisors', function (Blueprint $table) {
            $table->dropColumn('supervisor_incentive');
            $table->dropColumn('incentive_type');
        });
    }
}
