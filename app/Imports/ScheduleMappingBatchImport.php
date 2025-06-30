<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use App\Models\ScheduleCabinMapping;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ScheduleMappingBatchImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    public function mapping(): array
    {
        return [
            'id',
            'cabin_no',
            'adult_fare',
            'child_fare',
            'infant_fare',
            'owner',
            'counter_id',
            'service_charge',
            'service_charge_type'
        ];
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $collection->each(function($item, $key) {
            ScheduleCabinMapping::where('id', $item['id'])->update([
                'ownership' => strtolower($item['owner']),
                'fare' => $item['adult_fare'],
                'child_fare' => $item['child_fare'],
                'infant_fare' => $item['infant_fare'],
                'ghat_id' => $item['counter_id'],
                'service_charge' => $item['service_charge'],
                'service_charge_type' => $item['service_charge_type']
            ]);
        });
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
