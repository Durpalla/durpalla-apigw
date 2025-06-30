<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ScheduleReportExport implements FromView, ShouldAutoSize
{
    private $reports;
    private $title;
    public function __construct($reports, $title)
    {
        $this->reports = $reports;
        $this->title = $title;
    }

    public function view(): View
    {
        return view('exports.schedule-report', [
            'reports' => $this->reports,
            'title' => $this->title
        ]);
    }

    public static function afterSheet(AfterSheet $event)
    {
        //
    }
}
