<?php

namespace App\Imports;

use App\Constants\AppConst;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cabin;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SeatCabinImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    protected $launch;
    protected $type;
    private $user;

    public function __construct($launch, $type)
    {
        $this->launch = $launch;
        $this->type = $type;
        $this->user = auth()->user();
    }

    /**
     * @return array
     */

//    public function mapping(): array
//    {
//        return [
//            'ownership',
//            'cabin_no',
//            'type_id',
//            'fare',
//            'child_fare',
//            'infant_fare',
//            'floor',
//            'cabin_row',
//            'cabin_position',
//            'passenger_capacity',
//            'is_reserved',
//            'type',
//            'counter',
//            'service_charge',
//            'service_charge_type'
//        ];
//    }

    public function model(array $row)
    {
        try {
            if($row['cabin_no']) {
                $floor = (int)$row['floor'];
                $counter = (array_key_exists('counter', $row)) ? (int)$row['counter'] : null;
                $itemExist = collect($this->launch->{$this->type . 's'})->first(function ($item, $key) use ($row) {
                    return ($item->cabin_no == (int)$row['cabin_no']);
                });
                if ($itemExist == null) {
                    $item = [
                        'vehicle_id' => $this->launch->id,
                        'marchant_id' => $this->launch->merchant_id,
                        'ownership' => ($row['ownership'] == 'merchant') ? 'merchant' : AppConst::OWNER,
                        'cabin_no' => $row['cabin_no'],
                        'type_id' => (int)$row['type_id'],
                        'fare' => abs($row['fare']),
                        'child_fare' => abs($row['child_fare']) ?? $row['fare'],
                        'infant_fare' => abs($row['infant_fare']) ?? $row['fare'],
                        'floor' => (int)$row['floor'],
                        'cabin_row' => (int)$row['cabin_row'],
                        'cabin_position' => (int)$row['cabin_position'],
                        'passenger_capacity' => (int)$row['passenger_capacity'],
                        'is_reserved' => (int)$row['is_reserved'],
                        'type' => ($row['type'] == 'seat') ? 'seat' : 'cabin',
                        'created_by' => $this->user->id,
                        'ghat_id' => $counter,
                        'service_charge' => $row['service_charge'],
                        'service_charge_type' => $row['service_charge_type']
                    ];

                    Cabin::create($item);
                } else {
                    $itemExist->update([
                        'cabin_position' => $row['cabin_position'],
                        'cabin_row' => $row['cabin_row'],
                        'floor' => $row['floor'],
                        'type_id' => $row['type_id'],
                        'fare' => $row['fare'],
                        'passenger_capacity' => (int)$row['passenger_capacity'],
                        'is_reserved' => (int)$row['is_reserved'],
                        'ghat_id' => $counter,
                        'ownership' => ($row['ownership'] == null) ? 'merchant' : strtolower($row['ownership']),
                        'service_charge' => $row['service_charge'] ?? 0,
                        'service_charge_type' => $row['service_charge_type'] ?? AppConst::DEFAULT_SERVICE_CHARGE_TYPE
                    ]);
                }
            }
            return null;
        } catch (\Exception $exception) {
            dd($exception);
            Log::error('SeatCabinImport Error: ' . $exception->getMessage() . ' Row: ' . json_encode($row));
            return null;
        }
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
