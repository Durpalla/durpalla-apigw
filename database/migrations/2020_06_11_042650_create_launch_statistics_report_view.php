<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// use DB;

class CreateLaunchStatisticsReportView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // DB::statement("CREATE VIEW LaunchStatisticsReportView AS
        //     SELECT *,
        //     (
        //         SELECT GROUP_CONCAT(DISTINCT id SEPARATOR ',')
        //         FROM people AS p
        //         WHERE p.company_id = c.id
        //     ) AS person_ids
        //     FROM companies AS c");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // DB::statement("DROP VIEW LaunchStatisticsReportView");
    }
}