<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use App\Models\ScheduleCabinMapping;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ScheduleMappingBatchExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private $schedule_id;
    private $type;
    public function __construct($schedule_id, $type = 'cabin')
    {
        $this->schedule_id = $schedule_id;
        $this->type = $type;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cabin No',
            'Adult Fare',
            'Child Fare',
            'Infant Fare',
            'Type',
            'Owner',
            'Counter ID',
            'Service Charge',
            'Service Charge Type'
        ];
    }

    /**
    * @return Collection
    */
    public function collection(): Collection
    {
        return ScheduleCabinMapping::with(['cabin'])->select('id', 'cabin_id', 'fare', 'child_fare', 'infant_fare', 'type', 'ownership', 'ghat_id')
            ->where(['type' => $this->type, 'schedule_id' => $this->schedule_id])
            ->get()
            ->map(function($item, $key) {
               return [
                   'id' => $item->id,
                   'cabin_no' => $item->cabin->cabin_no,
                   'adult_fare' => $item->fare,
                   'child_fare' => $item->child_fare,
                   'infant_fare' => $item->infant_fare,
                   'type' => $item->type,
                   'owner' => $item->ownership,
                   'counter_id' => $item->ghat_id,
                   'service_charge' => $item->service_charge,
                   'service_charge_type' => $item->service_charge_type
               ];
            });
    }
}
