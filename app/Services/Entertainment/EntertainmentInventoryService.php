<?php

namespace App\Services\Entertainment;

use App\Models\EntertainmentInventory;
use App\Models\EntertainmentSlotTemplate;
use App\Models\EntertainmentTicketType;
use App\Models\EntertainmentVenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class EntertainmentInventoryService
{
    /**
     * @param  list<array{ticket_type_id:int,qty:int}>  $lines
     * @return list<array{ticket_type:EntertainmentTicketType,qty:int,unit_price:float,inventory:EntertainmentInventory}>
     */
    public function resolveLines(EntertainmentVenue $venue, ?string $visitDate, ?int $slotTemplateId, array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('At least one ticket line is required.');
        }

        $date = $visitDate ? Carbon::parse($visitDate)->toDateString() : now()->toDateString();
        if ($venue->capacity_mode === 'slot' && ! $slotTemplateId) {
            throw new \InvalidArgumentException('A time slot is required for this venue.');
        }

        if ($slotTemplateId) {
            $slot = EntertainmentSlotTemplate::query()
                ->where('venue_id', $venue->id)
                ->whereKey($slotTemplateId)
                ->active()
                ->first();
            if (! $slot) {
                throw new \InvalidArgumentException('Invalid time slot.');
            }
        }

        $resolved = [];
        foreach ($lines as $line) {
            $typeId = (int) ($line['ticket_type_id'] ?? 0);
            $qty = max(1, (int) ($line['qty'] ?? 1));
            $type = EntertainmentTicketType::query()
                ->where('venue_id', $venue->id)
                ->whereKey($typeId)
                ->active()
                ->first();
            if (! $type) {
                throw new \InvalidArgumentException('Invalid ticket type.');
            }

            $inventory = $this->ensureRow($venue, $type->id, $slotTemplateId, $date);
            if ($inventory->stop_sale) {
                throw new \RuntimeException('Tickets are stopped for this date.');
            }
            if ($inventory->available() < $qty) {
                throw new \RuntimeException('Not enough capacity for '.$type->name.'.');
            }

            $unit = $inventory->price_override !== null
                ? (float) $inventory->price_override
                : (float) $type->base_price;

            $resolved[] = [
                'ticket_type' => $type,
                'qty' => $qty,
                'unit_price' => $unit,
                'inventory' => $inventory,
            ];
        }

        return $resolved;
    }

    public function ensureRow(
        EntertainmentVenue $venue,
        ?int $ticketTypeId,
        ?int $slotTemplateId,
        string $visitDate,
    ): EntertainmentInventory {
        $capacity = (int) ($venue->default_daily_capacity ?: 100);
        if ($slotTemplateId) {
            $slotCap = EntertainmentSlotTemplate::query()->whereKey($slotTemplateId)->value('capacity');
            if ($slotCap !== null) {
                $capacity = (int) $slotCap;
            }
        }

        return EntertainmentInventory::query()->firstOrCreate(
            [
                'venue_id' => $venue->id,
                'ticket_type_id' => $ticketTypeId,
                'slot_template_id' => $slotTemplateId,
                'visit_date' => $visitDate,
            ],
            [
                'capacity' => $capacity,
                'sold' => 0,
                'held' => 0,
                'stop_sale' => false,
            ]
        );
    }

    /**
     * @param  list<array{inventory:EntertainmentInventory,qty:int}>  $resolved
     */
    public function applyHold(array $resolved): void
    {
        foreach ($resolved as $row) {
            /** @var EntertainmentInventory $inv */
            $inv = EntertainmentInventory::query()->lockForUpdate()->findOrFail($row['inventory']->id);
            if ($inv->stop_sale || $inv->available() < (int) $row['qty']) {
                throw new \RuntimeException('Capacity changed; please try again.');
            }
            $inv->increment('held', (int) $row['qty']);
        }
    }

    /**
     * @param  list<array{inventory_id?:int,ticket_type_id?:int,qty:int}>  $lines
     */
    public function releaseHoldLines(EntertainmentVenue $venue, ?string $visitDate, ?int $slotId, array $lines): void
    {
        $date = $visitDate ? Carbon::parse($visitDate)->toDateString() : now()->toDateString();
        foreach ($lines as $line) {
            $qty = max(1, (int) ($line['qty'] ?? 1));
            $invId = (int) ($line['inventory_id'] ?? 0);
            $q = EntertainmentInventory::query()->lockForUpdate();
            if ($invId > 0) {
                $inv = $q->find($invId);
            } else {
                $inv = $q->where('venue_id', $venue->id)
                    ->where('ticket_type_id', (int) ($line['ticket_type_id'] ?? 0))
                    ->where('slot_template_id', $slotId)
                    ->whereDate('visit_date', $date)
                    ->first();
            }
            if ($inv) {
                $inv->update(['held' => max(0, (int) $inv->held - $qty)]);
            }
        }
    }

    /**
     * @param  list<array{inventory:EntertainmentInventory,qty:int}>  $resolved
     */
    public function consumeHold(array $resolved): void
    {
        foreach ($resolved as $row) {
            $inv = EntertainmentInventory::query()->lockForUpdate()->findOrFail($row['inventory']->id);
            $qty = (int) $row['qty'];
            $inv->update([
                'held' => max(0, (int) $inv->held - $qty),
                'sold' => (int) $inv->sold + $qty,
            ]);
        }
    }

    public function upsertDay(EntertainmentVenue $venue, array $input): EntertainmentInventory
    {
        $date = Carbon::parse((string) $input['visit_date'])->toDateString();
        $row = $this->ensureRow(
            $venue,
            isset($input['ticket_type_id']) ? (int) $input['ticket_type_id'] : null,
            isset($input['slot_template_id']) ? (int) $input['slot_template_id'] : null,
            $date,
        );

        $row->update([
            'capacity' => (int) ($input['capacity'] ?? $row->capacity),
            'stop_sale' => (bool) ($input['stop_sale'] ?? $row->stop_sale),
            'price_override' => array_key_exists('price_override', $input)
                ? ($input['price_override'] === null ? null : (float) $input['price_override'])
                : $row->price_override,
        ]);

        return $row->fresh();
    }
}
