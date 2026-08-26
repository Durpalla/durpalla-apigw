<?php

namespace App\Services\Vehicle;

use App\Constants\AppConst;
use App\Models\Cabin;
use App\Models\CabinType;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CSV layout import for merchant desk (no maatwebsite/excel).
 * Same column headings as admin SeatCabinImport / merchant sample CSVs.
 */
final class VehicleLayoutCsvImporter
{
    /** @var array<string, int> */
    private array $typeIdCache = [];

    /**
     * @param  'seat'|'cabin'|'sofa'  $defaultType
     * @return int number of rows applied
     */
    public function import(Vehicle $vehicle, string $defaultType, UploadedFile $file, ?int $actorId = null): int
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \InvalidArgumentException('Unable to read uploaded file.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Unable to open uploaded file.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || $header === []) {
                throw new \InvalidArgumentException('CSV is empty or missing a header row.');
            }

            $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
            $applied = 0;

            DB::transaction(function () use ($handle, $header, $vehicle, $defaultType, $actorId, &$applied) {
                while (($cells = fgetcsv($handle)) !== false) {
                    if ($cells === [null] || $this->rowIsBlank($cells)) {
                        continue;
                    }
                    $assoc = [];
                    foreach ($header as $i => $key) {
                        if ($key === '') {
                            continue;
                        }
                        $assoc[$key] = $cells[$i] ?? null;
                    }
                    if ($this->applyRow($vehicle, $defaultType, $assoc, $actorId)) {
                        $applied++;
                    }
                }
            }, 3);
        } finally {
            fclose($handle);
        }

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyRow(Vehicle $vehicle, string $defaultType, array $row, ?int $actorId): bool
    {
        $row = $this->normalizeRow($row);
        $cabinNo = trim((string) ($row['cabin_no'] ?? ''));
        if ($cabinNo === '') {
            return false;
        }

        $ghatId = array_key_exists('counter', $row) && $row['counter'] !== null && $row['counter'] !== ''
            ? (int) $row['counter']
            : 0;

        $itemExist = Cabin::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('cabin_no', $cabinNo)
            ->first();

        $rowType = strtolower(trim((string) ($row['type'] ?? '')));
        $itemType = in_array($rowType, ['seat', 'cabin', 'sofa'], true)
            ? $rowType
            : ($defaultType === 'seat' ? 'seat' : ($defaultType === 'sofa' ? 'sofa' : 'cabin'));

        $typeId = (int) ($row['type_id'] ?? 0);
        if ($typeId <= 0) {
            $typeId = $this->resolveTypeId($vehicle, $itemType);
        }

        $fare = abs((float) ($row['fare'] ?? 0));
        $childFare = abs((float) ($row['child_fare'] ?? $fare));
        $infantFare = abs((float) ($row['infant_fare'] ?? $fare));

        if ($itemExist === null) {
            Cabin::query()->create([
                'vehicle_id' => $vehicle->id,
                'marchant_id' => $vehicle->merchant_id,
                'ownership' => (($row['ownership'] ?? '') === 'merchant') ? 'merchant' : AppConst::OWNER,
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
                'created_by' => $actorId,
                'ghat_id' => $ghatId,
                'service_charge' => $row['service_charge'] ?? 0,
                'service_charge_type' => $row['service_charge_type'] ?? AppConst::DEFAULT_SERVICE_CHARGE_TYPE,
            ]);
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

        return true;
    }

    /**
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

    private function resolveTypeId(Vehicle $vehicle, string $itemType): int
    {
        $cacheKey = $vehicle->id.'_'.$itemType;
        if (isset($this->typeIdCache[$cacheKey])) {
            return $this->typeIdCache[$cacheKey];
        }

        $vehicleType = (string) ($vehicle->vehicle_type ?? '');

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

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return strtolower(trim(str_replace([' ', '-'], '_', $header)));
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function rowIsBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
