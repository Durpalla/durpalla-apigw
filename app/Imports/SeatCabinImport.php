<?php

namespace App\Imports;

use App\Constants\AppConst;
use App\Models\Cabin;
use App\Models\CabinType;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SeatCabinImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    protected $launch;

    protected $type;

    private $user;

    /** @var array<string, int> */
    private array $typeIdCache = [];

    public function __construct($launch, $type)
    {
        $this->launch = $launch;
        $this->type = $type;
        $this->user = auth()->user();
    }

    public function model(array $row)
    {
        try {
            $row = $this->normalizeRow($row);
            $cabinNo = trim((string) ($row['cabin_no'] ?? ''));
            if ($cabinNo === '') {
                return null;
            }

            $ghatId = array_key_exists('counter', $row) && $row['counter'] !== null && $row['counter'] !== ''
                ? (int) $row['counter']
                : 0;

            $itemExist = Cabin::query()
                ->where('vehicle_id', $this->launch->id)
                ->where('cabin_no', $cabinNo)
                ->first();

            $rowType = strtolower(trim((string) ($row['type'] ?? '')));
            $itemType = in_array($rowType, ['seat', 'cabin', 'sofa'], true)
                ? $rowType
                : ($this->type === 'seat' ? 'seat' : ($this->type === 'sofa' ? 'sofa' : 'cabin'));

            $typeId = (int) ($row['type_id'] ?? 0);
            if ($typeId <= 0) {
                $typeId = $this->resolveTypeId($itemType);
            }

            $fare = abs((float) ($row['fare'] ?? 0));
            $childFare = abs((float) ($row['child_fare'] ?? $fare));
            $infantFare = abs((float) ($row['infant_fare'] ?? $fare));

            if ($itemExist == null) {
                $item = [
                    'vehicle_id' => $this->launch->id,
                    'marchant_id' => $this->launch->merchant_id,
                    'ownership' => ($row['ownership'] == 'merchant') ? 'merchant' : AppConst::OWNER,
                    'cabin_no' => $cabinNo,
                    'type_id' => $typeId,
                    'fare' => $fare,
                    'child_fare' => $childFare,
                    'infant_fare' => $infantFare,
                    'floor' => (int) ($row['floor'] ?? 1),
                    'cabin_row' => (int) ($row['cabin_row'] ?? 0),
                    'cabin_position' => (int) ($row['cabin_position'] ?? 0),
                    'passenger_capacity' => (int) ($row['passenger_capacity'] ?? 1),
                    'is_reserved' => (int) ($row['is_reserved'] ?? 0),
                    'type' => $itemType,
                    'created_by' => $this->user->id,
                    'ghat_id' => $ghatId,
                    'service_charge' => $row['service_charge'] ?? 0,
                    'service_charge_type' => $row['service_charge_type'] ?? AppConst::DEFAULT_SERVICE_CHARGE_TYPE,
                ];

                Cabin::create($item);
            } else {
                $itemExist->update([
                    'cabin_position' => (int) ($row['cabin_position'] ?? $itemExist->cabin_position),
                    'cabin_row' => (int) ($row['cabin_row'] ?? $itemExist->cabin_row),
                    'floor' => (int) ($row['floor'] ?? $itemExist->floor),
                    'type_id' => $typeId,
                    'fare' => $fare,
                    'passenger_capacity' => (int) ($row['passenger_capacity'] ?? $itemExist->passenger_capacity),
                    'is_reserved' => (int) ($row['is_reserved'] ?? $itemExist->is_reserved),
                    'ghat_id' => $ghatId,
                    'ownership' => ($row['ownership'] == null) ? 'merchant' : strtolower((string) $row['ownership']),
                    'service_charge' => $row['service_charge'] ?? 0,
                    'service_charge_type' => $row['service_charge_type'] ?? AppConst::DEFAULT_SERVICE_CHARGE_TYPE,
                    'type' => $itemType,
                ]);
            }

            return null;
        } catch (\Exception $exception) {
            Log::error('SeatCabinImport Error: '.$exception->getMessage().' Row: '.json_encode($row));
            throw $exception;
        }
    }

    /**
     * Accept both legacy admin batch columns and merchant sample CSV headings.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $cabinNo = $row['cabin_no'] ?? $row['seat_no'] ?? $row['sofa_no'] ?? null;

        return [
            'cabin_no' => $cabinNo,
            'type_id' => $row['type_id'] ?? null,
            'cabin_row' => $row['cabin_row'] ?? $row['row'] ?? null,
            'cabin_position' => $row['cabin_position'] ?? $row['column'] ?? null,
            'floor' => $row['floor'] ?? 1,
            'fare' => $row['fare'] ?? 0,
            'child_fare' => $row['child_fare'] ?? null,
            'infant_fare' => $row['infant_fare'] ?? null,
            'passenger_capacity' => $row['passenger_capacity'] ?? $row['capacity'] ?? 1,
            'is_reserved' => $row['is_reserved'] ?? 0,
            'ownership' => $row['ownership'] ?? 'merchant',
            'type' => $row['type'] ?? $row['seat_type'] ?? $row['cabin_type'] ?? $row['sofa_type'] ?? null,
            'counter' => $row['counter'] ?? null,
            'service_charge' => $row['service_charge'] ?? 0,
            'service_charge_type' => $row['service_charge_type'] ?? AppConst::DEFAULT_SERVICE_CHARGE_TYPE,
        ];
    }

    private function resolveTypeId(string $itemType): int
    {
        $cacheKey = $this->launch->id.'_'.$itemType;
        if (isset($this->typeIdCache[$cacheKey])) {
            return $this->typeIdCache[$cacheKey];
        }

        $vehicleType = (string) ($this->launch->vehicle_type ?? '');

        $type = CabinType::query()
            ->where('type', $itemType)
            ->where(function ($query) use ($vehicleType) {
                $query->where('service_type', $vehicleType)
                    ->orWhereNull('service_type');
            })
            ->orderByRaw('CASE WHEN service_type = ? THEN 0 ELSE 1 END', [$vehicleType])
            ->first();

        if ($type === null) {
            $type = CabinType::query()->where('type', $itemType)->first();
        }

        if ($type === null) {
            throw new \RuntimeException("No {$itemType} type is configured. Please set up cabin types first.");
        }

        $this->typeIdCache[$cacheKey] = (int) $type->id;

        return $this->typeIdCache[$cacheKey];
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
