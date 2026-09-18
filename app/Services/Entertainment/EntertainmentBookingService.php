<?php

namespace App\Services\Entertainment;

use App\Constants\AppConst;
use App\Models\AgentCommissionAccrual;
use App\Models\Booking;
use App\Models\BookingEntertainmentItem;
use App\Models\Customer;
use App\Models\EntertainmentHold;
use App\Models\EntertainmentImage;
use App\Models\EntertainmentReview;
use App\Models\EntertainmentTicket;
use App\Models\EntertainmentTicketEvent;
use App\Models\EntertainmentVenue;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EntertainmentBookingService
{
    public function __construct(
        private readonly EntertainmentInventoryService $inventory,
        private readonly EntertainmentReviewEligibilityService $reviews,
    ) {}

    /** @return list<array<string, mixed>> */
    public function homeTop(Request $request): array
    {
        if (! Schema::hasTable('entertainment_venues')) {
            return [];
        }

        $limit = max(1, min(20, (int) $request->query('limit', 8)));
        $venues = EntertainmentVenue::query()
            ->active()
            ->with(['images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")->orderBy('sort_order')])
            ->orderByDesc('rating_avg')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $venues->map(fn (EntertainmentVenue $v) => $this->cardPayload($v))->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function search(Request $request): array
    {
        if (! Schema::hasTable('entertainment_venues')) {
            return [];
        }

        $limit = max(1, min(100, (int) $request->query('limit', 30)));
        $q = EntertainmentVenue::query()
            ->active()
            ->with(['images' => fn ($iq) => $iq->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")->orderBy('sort_order')]);

        if ($request->filled('q') || $request->filled('search')) {
            $term = '%'.trim((string) ($request->input('q') ?: $request->input('search'))).'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('name', 'LIKE', $term)
                    ->orWhere('tagline', 'LIKE', $term)
                    ->orWhere('short_description', 'LIKE', $term)
                    ->orWhere('address', 'LIKE', $term);
            });
        }
        if ($request->filled('attraction_type') || $request->filled('type')) {
            $q->where('attraction_type', trim((string) ($request->input('attraction_type') ?: $request->input('type'))));
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->input('city_id'));
        }

        return $q->orderByDesc('rating_avg')->orderByDesc('id')->limit($limit)
            ->get()
            ->map(fn (EntertainmentVenue $v) => $this->cardPayload($v))
            ->values()
            ->all();
    }

    public function show(int $venueId, ?int $userId = null): ?array
    {
        if (! Schema::hasTable('entertainment_venues')) {
            return null;
        }

        $venue = EntertainmentVenue::query()
            ->active()
            ->with([
                'images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")->orderBy('sort_order'),
                'ticketTypes' => fn ($q) => $q->active(),
                'slotTemplates' => fn ($q) => $q->active(),
                'reviews' => fn ($q) => $q->orderByDesc('id')->limit(20),
                'city',
            ])
            ->find($venueId);

        if (! $venue) {
            return null;
        }

        $payload = $this->detailPayload($venue);
        if ($userId) {
            $payload['can_review'] = $this->reviews->userCanReviewVenue($userId, $venueId);
        }

        return $payload;
    }

    /**
     * @param  array{venue_id:int,visit_date?:string,slot_template_id?:int,lines:list<array{ticket_type_id:int,qty:int}>}  $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $venue = EntertainmentVenue::query()->active()->findOrFail((int) $input['venue_id']);
        $resolved = $this->inventory->resolveLines(
            $venue,
            $input['visit_date'] ?? null,
            isset($input['slot_template_id']) ? (int) $input['slot_template_id'] : null,
            $input['lines'] ?? [],
        );

        $lines = [];
        $total = 0.0;
        $qty = 0;
        foreach ($resolved as $row) {
            $lineTotal = round($row['unit_price'] * $row['qty'], 2);
            $total += $lineTotal;
            $qty += $row['qty'];
            $lines[] = [
                'ticket_type_id' => $row['ticket_type']->id,
                'name' => $row['ticket_type']->name,
                'code' => $row['ticket_type']->code,
                'qty' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'line_total' => $lineTotal,
                'available' => $row['inventory']->available(),
            ];
        }

        return [
            'venue_id' => $venue->id,
            'visit_date' => $input['visit_date'] ?? now()->toDateString(),
            'slot_template_id' => $input['slot_template_id'] ?? null,
            'lines' => $lines,
            'total_qty' => $qty,
            'total_price' => round($total, 2),
            'validity_days' => (int) $venue->validity_days,
            'max_redemptions' => (int) $venue->max_redemptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createHold(
        ?Customer $user,
        array $input,
        string $idempotencyKey,
        ?int $agentId = null,
        ?int $merchantId = null,
    ): EntertainmentHold {
        $existingQ = EntertainmentHold::query()->where('idempotency_key', $idempotencyKey);
        if ($user) {
            $existingQ->where('user_id', $user->id);
        } elseif ($agentId) {
            $existingQ->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $existingQ->where('merchant_id', $merchantId);
        }
        $existing = $existingQ->first();
        if ($existing) {
            return $existing;
        }

        $venue = EntertainmentVenue::query()->active()->findOrFail((int) $input['venue_id']);
        if ($merchantId && (int) $venue->merchant_id !== (int) $merchantId) {
            throw new \RuntimeException('Venue does not belong to this merchant.');
        }

        $visitDate = isset($input['visit_date']) ? Carbon::parse((string) $input['visit_date'])->toDateString() : now()->toDateString();
        $slotId = isset($input['slot_template_id']) ? (int) $input['slot_template_id'] : null;
        $resolved = $this->inventory->resolveLines($venue, $visitDate, $slotId, $input['lines'] ?? []);

        $linesJson = [];
        $total = 0.0;
        $qty = 0;
        foreach ($resolved as $row) {
            $lineTotal = round($row['unit_price'] * $row['qty'], 2);
            $total += $lineTotal;
            $qty += $row['qty'];
            $linesJson[] = [
                'ticket_type_id' => $row['ticket_type']->id,
                'inventory_id' => $row['inventory']->id,
                'code' => $row['ticket_type']->code,
                'name' => $row['ticket_type']->name,
                'qty' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'validity_days' => $row['ticket_type']->validity_days ?? $venue->validity_days,
                'max_redemptions' => $row['ticket_type']->max_redemptions ?? $venue->max_redemptions,
            ];
        }

        $ttl = max(5, (int) config('entertainment.hold_ttl_minutes', 15));
        $guest = is_array($input['guest'] ?? null) ? $input['guest'] : null;

        return DB::transaction(function () use ($user, $venue, $visitDate, $slotId, $resolved, $linesJson, $total, $qty, $ttl, $idempotencyKey, $agentId, $merchantId, $guest) {
            $this->inventory->applyHold($resolved);

            return EntertainmentHold::create([
                'venue_id' => $venue->id,
                'user_id' => $user?->id,
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'visit_date' => $visitDate,
                'slot_template_id' => $slotId,
                'lines_json' => $linesJson,
                'total_qty' => $qty,
                'total_price' => round($total, 2),
                'status' => EntertainmentHold::STATUS_PENDING,
                'expires_at' => now()->addMinutes($ttl),
                'guest_json' => $guest,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function releaseHold(?Customer $user, int $holdId, ?int $agentId = null, ?int $merchantId = null): bool
    {
        $q = EntertainmentHold::query()
            ->where('id', $holdId)
            ->where('status', EntertainmentHold::STATUS_PENDING);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }

        $hold = $q->first();
        if (! $hold) {
            return false;
        }

        DB::transaction(function () use ($hold) {
            $venue = EntertainmentVenue::query()->find($hold->venue_id);
            if ($venue) {
                $this->inventory->releaseHoldLines(
                    $venue,
                    $hold->visit_date?->toDateString(),
                    $hold->slot_template_id,
                    $hold->lines_json ?? [],
                );
            }
            $hold->update(['status' => EntertainmentHold::STATUS_CANCELLED]);
        });

        return true;
    }

    /**
     * @param  array{mode?:string,method?:string,amount_paid?:float|int|string}|null  $paymentInput
     * @return array{booking: Booking, payment: Payment, item: BookingEntertainmentItem, tickets: list<EntertainmentTicket>}
     */
    public function confirmFromHold(
        ?Customer $user,
        int $holdId,
        ?string $platform = 'web',
        ?array $guest = null,
        ?int $agentId = null,
        ?int $merchantId = null,
        ?array $paymentInput = null,
    ): array {
        $q = EntertainmentHold::query()->where('id', $holdId);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }
        $hold = $q->firstOrFail();

        if ($hold->status === EntertainmentHold::STATUS_CONSUMED) {
            $item = BookingEntertainmentItem::query()->where('hold_id', $hold->id)->latest('id')->first();
            if ($item) {
                $booking = $item->booking;
                $payment = Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->firstOrFail();
                $tickets = EntertainmentTicket::query()->where('booking_id', $booking->id)->get()->all();

                return compact('booking', 'payment', 'item', 'tickets');
            }
        }

        if ($hold->status !== EntertainmentHold::STATUS_PENDING || ($hold->expires_at && now()->greaterThan($hold->expires_at))) {
            throw new \RuntimeException('Hold is not valid');
        }

        $guestPayload = [
            'name' => trim((string) ($guest['name'] ?? '')) ?: (string) ($user->name ?? ''),
            'mobile' => trim((string) ($guest['mobile'] ?? '')) ?: (string) ($user->mobile ?? ''),
            'email' => trim((string) ($guest['email'] ?? '')) ?: (string) ($user->email ?? ''),
        ];

        return DB::transaction(function () use ($user, $hold, $platform, $guestPayload, $paymentInput, $agentId) {
            $venue = EntertainmentVenue::query()->lockForUpdate()->findOrFail($hold->venue_id);
            $lines = $hold->lines_json ?? [];
            $resolved = [];
            foreach ($lines as $line) {
                $inv = $this->inventory->ensureRow(
                    $venue,
                    (int) ($line['ticket_type_id'] ?? 0),
                    $hold->slot_template_id,
                    $hold->visit_date?->toDateString() ?? now()->toDateString(),
                );
                $resolved[] = ['inventory' => $inv, 'qty' => (int) ($line['qty'] ?? 1)];
            }
            $this->inventory->consumeHold($resolved);

            $total = (float) $hold->total_price;
            $bookingPlatform = $this->normalizePlatform($platform);
            $payMode = strtolower(trim((string) ($paymentInput['mode'] ?? 'none')));
            $amountPaid = (float) ($paymentInput['amount_paid'] ?? $paymentInput['amountPaid'] ?? 0);
            if ($payMode === 'full') {
                $amountPaid = $total;
            }
            if ($payMode === 'none') {
                $amountPaid = 0;
            }
            $amountPaid = max(0, min($total, round($amountPaid, 2)));
            $isPaid = $amountPaid + 0.001 >= $total;
            $bookingStatus = $isPaid ? AppConst::BOOKING_COMPLETE : AppConst::BOOKING_PENDING;

            $validityDays = (int) $venue->validity_days;
            $purchasedAt = now();
            $validUntil = $purchasedAt->copy()->timezone($venue->timezone ?: 'Asia/Dhaka')
                ->addDays($validityDays)
                ->endOfDay();

            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $user?->id,
                'user_id' => $user?->id,
                'referring_agent_id' => $agentId,
                'total_amount' => $total,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) (function_exists('getOption') ? getOption('vat_amount', 0) : 0),
                'vat_total' => 0,
                'charge_amount' => 0,
                'charge_total' => 0,
                'booking_party' => 'durpalla',
                'platform' => $bookingPlatform,
                'status' => $bookingStatus,
                'service_type' => 'entertainment',
                'from_date' => $hold->visit_date?->toDateString(),
                'to_date' => $hold->visit_date?->toDateString(),
            ]);

            $item = BookingEntertainmentItem::create([
                'booking_id' => $booking->id,
                'venue_id' => $venue->id,
                'hold_id' => $hold->id,
                'visit_date' => $hold->visit_date,
                'slot_template_id' => $hold->slot_template_id,
                'venue_name' => $venue->name,
                'attraction_type' => $venue->attraction_type,
                'lines_json' => $lines,
                'total_qty' => (int) $hold->total_qty,
                'unit_price' => $hold->total_qty > 0 ? round($total / (int) $hold->total_qty, 2) : $total,
                'total_price' => $total,
                'validity_days' => $validityDays,
                'valid_from' => $purchasedAt,
                'valid_until' => $validUntil,
            ]);

            $tickets = [];
            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['qty'] ?? 1));
                $lineValidity = (int) ($line['validity_days'] ?? $validityDays);
                $lineMaxRedeem = (int) ($line['max_redemptions'] ?? $venue->max_redemptions);
                $lineValidUntil = $purchasedAt->copy()->timezone($venue->timezone ?: 'Asia/Dhaka')
                    ->addDays($lineValidity)
                    ->endOfDay();
                for ($i = 0; $i < $qty; $i++) {
                    $tickets[] = EntertainmentTicket::create([
                        'booking_id' => $booking->id,
                        'booking_item_id' => $item->id,
                        'venue_id' => $venue->id,
                        'ticket_type_id' => (int) ($line['ticket_type_id'] ?? 0),
                        'inventory_id' => (int) ($line['inventory_id'] ?? 0) ?: null,
                        'code' => strtoupper(Str::random(10)),
                        'qr_token' => (string) Str::uuid(),
                        'visitor_type' => (string) ($line['code'] ?? 'adult'),
                        'visitor_label' => (string) ($line['name'] ?? 'Ticket'),
                        'price' => (float) ($line['unit_price'] ?? 0),
                        'purchased_at' => $purchasedAt,
                        'valid_from' => $purchasedAt,
                        'valid_until' => $lineValidUntil,
                        'max_redemptions' => $lineMaxRedeem,
                        'redemption_count' => 0,
                        'status' => EntertainmentTicket::STATUS_VALID,
                    ]);
                }
            }

            $hold->update([
                'status' => EntertainmentHold::STATUS_CONSUMED,
                'guest_json' => $guestPayload,
            ]);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $user?->id,
                'status' => $isPaid ? 'success' : ($amountPaid > 0 ? 'advance' : 'pending'),
                'payment_method' => $paymentInput['method'] ?? ($isPaid ? 'cash' : null),
                'paid_amount' => $amountPaid > 0 ? $amountPaid : $total,
                'dues' => max(0, round($total - $amountPaid, 2)),
                'store_amount' => $amountPaid,
            ]);

            if ($agentId && $isPaid) {
                $this->accrueAgentCommission((int) $agentId, $booking, $venue, $total);
            }

            return compact('booking', 'payment', 'item', 'tickets');
        });
    }

    public function availability(int $venueId, string $from, string $to): array
    {
        $venue = EntertainmentVenue::query()->active()->findOrFail($venueId);
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        if ($end->lt($start) || $start->diffInDays($end) > 62) {
            throw new \InvalidArgumentException('Invalid date range.');
        }

        $rows = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            if ($venue->capacity_mode === 'slot') {
                $slots = [];
                foreach ($venue->slotTemplates()->active()->get() as $slot) {
                    $inv = $this->inventory->ensureRow($venue, null, (int) $slot->id, $date);
                    $slots[] = [
                        'slot_template_id' => $slot->id,
                        'label' => $slot->label ?: (substr((string) $slot->starts_at, 0, 5).'–'.substr((string) $slot->ends_at, 0, 5)),
                        'starts_at' => $slot->starts_at,
                        'ends_at' => $slot->ends_at,
                        'capacity' => (int) $inv->capacity,
                        'available' => $inv->available(),
                        'stop_sale' => (bool) $inv->stop_sale,
                    ];
                }
                $rows[] = ['date' => $date, 'slots' => $slots];
            } else {
                $inv = $this->inventory->ensureRow($venue, null, null, $date);
                $rows[] = [
                    'date' => $date,
                    'capacity' => (int) $inv->capacity,
                    'available' => $inv->available(),
                    'stop_sale' => (bool) $inv->stop_sale,
                ];
            }
        }

        return ['venue_id' => $venue->id, 'capacity_mode' => $venue->capacity_mode, 'days' => $rows];
    }

    public function listReviews(int $venueId): array
    {
        return EntertainmentReview::query()
            ->where('venue_id', $venueId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (EntertainmentReview $r) => [
                'id' => $r->id,
                'author' => $r->author,
                'rating' => (float) $r->rating,
                'text' => $r->text,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function storeReview(Customer $user, int $venueId, float $rating, string $text): EntertainmentReview
    {
        if (! $this->reviews->userCanReviewVenue((int) $user->id, $venueId)) {
            throw new \RuntimeException(__('You can review this venue after you buy a paid ticket.'));
        }

        $bookingId = $this->reviews->findPaidBookingId((int) $user->id, $venueId);

        $review = EntertainmentReview::create([
            'venue_id' => $venueId,
            'user_id' => $user->id,
            'booking_id' => $bookingId,
            'rating' => $rating,
            'author' => trim((string) ($user->name ?? '')) ?: 'Guest',
            'text' => $text,
        ]);

        $avg = (float) EntertainmentReview::query()->where('venue_id', $venueId)->avg('rating');
        $count = (int) EntertainmentReview::query()->where('venue_id', $venueId)->count();
        EntertainmentVenue::query()->whereKey($venueId)->update([
            'rating_avg' => round($avg, 2),
            'review_count' => $count,
        ]);

        return $review;
    }

    public function lookupTicket(string $codeOrToken): ?EntertainmentTicket
    {
        $key = trim($codeOrToken);
        if ($key === '') {
            return null;
        }

        $ticket = EntertainmentTicket::query()
            ->with('venue')
            ->where(function ($q) use ($key) {
                $q->where('code', $key)->orWhere('qr_token', $key);
            })
            ->first();

        if ($ticket && $ticket->status === EntertainmentTicket::STATUS_VALID && $ticket->isExpiredNow()) {
            $ticket->update(['status' => EntertainmentTicket::STATUS_EXPIRED]);
            $ticket->refresh();
        }

        return $ticket;
    }

    public function redeemTicket(
        EntertainmentTicket $ticket,
        int $actorId,
        string $actorType = 'merchant_staff',
        bool $forceAccept = false,
        ?string $consentNote = null,
    ): EntertainmentTicket {
        return DB::transaction(function () use ($ticket, $actorId, $actorType, $forceAccept, $consentNote) {
            $ticket = EntertainmentTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status === EntertainmentTicket::STATUS_CANCELLED) {
                throw new \RuntimeException('Ticket is cancelled.');
            }
            if ($ticket->status === EntertainmentTicket::STATUS_USED
                || (int) $ticket->redemption_count >= (int) $ticket->max_redemptions) {
                throw new \RuntimeException('Ticket already used.');
            }

            $expired = $ticket->isExpiredNow() || $ticket->status === EntertainmentTicket::STATUS_EXPIRED;
            if ($expired && ! $forceAccept) {
                throw new \RuntimeException('Ticket expired. Project manager consent is required to accept.');
            }
            if ($expired && $forceAccept && trim((string) $consentNote) === '') {
                throw new \RuntimeException('Consent note is required to accept an expired ticket.');
            }

            $ticket->update([
                'status' => EntertainmentTicket::STATUS_USED,
                'redemption_count' => (int) $ticket->redemption_count + 1,
                'redeemed_at' => now(),
                'redeemed_by' => $actorId,
                'override_accepted' => $expired && $forceAccept,
            ]);

            EntertainmentTicketEvent::create([
                'ticket_id' => $ticket->id,
                'event_type' => $expired && $forceAccept ? 'override_accept' : 'redeem',
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'consent_note' => $consentNote,
            ]);

            return $ticket->fresh(['venue']);
        });
    }

    public function ticketPayload(EntertainmentTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'qr_token' => $ticket->qr_token,
            'visitor_type' => $ticket->visitor_type,
            'visitor_label' => $ticket->visitor_label,
            'status' => $ticket->status,
            'valid_from' => $ticket->valid_from?->toIso8601String(),
            'valid_until' => $ticket->valid_until?->toIso8601String(),
            'max_redemptions' => (int) $ticket->max_redemptions,
            'redemption_count' => (int) $ticket->redemption_count,
            'override_accepted' => (bool) $ticket->override_accepted,
            'venue' => $ticket->venue ? [
                'id' => $ticket->venue->id,
                'name' => $ticket->venue->name,
                'attraction_type' => $ticket->venue->attraction_type,
            ] : null,
        ];
    }

    public function formatHold(EntertainmentHold $hold): array
    {
        return [
            'id' => $hold->id,
            'venue_id' => $hold->venue_id,
            'visit_date' => $hold->visit_date?->toDateString(),
            'slot_template_id' => $hold->slot_template_id,
            'lines' => $hold->lines_json,
            'total_qty' => (int) $hold->total_qty,
            'total_price' => (float) $hold->total_price,
            'status' => $hold->status,
            'expires_at' => $hold->expires_at?->toIso8601String(),
        ];
    }

    private function accrueAgentCommission(int $agentId, Booking $booking, EntertainmentVenue $venue, float $total): void
    {
        if (! Schema::hasTable('agent_commission_accruals')) {
            return;
        }

        $type = strtolower((string) ($venue->agent_commission_type ?: 'percent'));
        $rate = (float) $venue->agent_commission;
        if ($rate <= 0) {
            return;
        }

        $amount = $type === 'fixed'
            ? round($rate, 2)
            : round($total * ($rate / 100), 2);

        if ($amount <= 0) {
            return;
        }

        AgentCommissionAccrual::query()->firstOrCreate(
            [
                'agent_id' => $agentId,
                'booking_id' => $booking->id,
                'source_key' => 'entertainment:'.$booking->id,
            ],
            [
                'booking_item_id' => null,
                'source_type' => 'entertainment_venue',
                'source_id' => $venue->id,
                'kind' => 'booking',
                'service_type' => 'entertainment',
                'base_amount' => $total,
                'rate' => $type === 'percent' ? $rate : 0,
                'incentive_type' => $type,
                'amount' => $amount,
                'eligible_at' => now(),
                'status' => AgentCommissionAccrual::STATUS_PENDING,
                'meta' => [
                    'venue_id' => $venue->id,
                    'commission_source' => 'venue_separate',
                ],
            ]
        );
    }

    private function cardPayload(EntertainmentVenue $venue): array
    {
        $cover = $venue->images->firstWhere('type', 'cover') ?? $venue->images->first();

        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'attraction_type' => $venue->attraction_type,
            'theme_preset' => $venue->resolvedThemePreset(),
            'tagline' => $venue->tagline,
            'short_description' => $venue->short_description,
            'capacity_mode' => $venue->capacity_mode,
            'validity_days' => (int) $venue->validity_days,
            'rating_avg' => (float) $venue->rating_avg,
            'review_count' => (int) $venue->review_count,
            'price_from' => (float) ($venue->ticketTypes()->active()->min('base_price') ?? 0),
            'cover_url' => $cover?->url,
            'city' => $venue->relationLoaded('city') && $venue->city ? [
                'id' => $venue->city->id,
                'name' => $venue->city->name ?? null,
            ] : null,
        ];
    }

    private function detailPayload(EntertainmentVenue $venue): array
    {
        return array_merge($this->cardPayload($venue), [
            'long_description' => $venue->long_description,
            'highlights' => $venue->highlights ?? [],
            'whats_included' => $venue->whats_included ?? [],
            'accent' => $venue->accent,
            'logo_url' => $venue->logo_url,
            'address' => $venue->address,
            'opening_hours' => $venue->opening_hours,
            'cancellation_policy' => $venue->cancellation_policy,
            'max_redemptions' => (int) $venue->max_redemptions,
            'agent_commission_type' => $venue->agent_commission_type,
            'images' => $venue->images->map(fn (EntertainmentImage $i) => [
                'id' => $i->id,
                'url' => $i->url,
                'type' => $i->type,
            ])->values()->all(),
            'ticket_types' => $venue->ticketTypes->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'base_price' => (float) $t->base_price,
                'min_age' => $t->min_age,
                'max_age' => $t->max_age,
                'validity_days' => $t->validity_days ?? $venue->validity_days,
                'max_redemptions' => $t->max_redemptions ?? $venue->max_redemptions,
            ])->values()->all(),
            'slot_templates' => $venue->slotTemplates->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'starts_at' => $s->starts_at,
                'ends_at' => $s->ends_at,
                'capacity' => $s->capacity,
            ])->values()->all(),
            'reviews' => $venue->reviews->map(fn (EntertainmentReview $r) => [
                'id' => $r->id,
                'author' => $r->author,
                'rating' => (float) $r->rating,
                'text' => $r->text,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
            'can_review' => false,
        ]);
    }

    private function normalizePlatform(?string $platform): string
    {
        $p = strtolower(trim((string) $platform));

        return match ($p) {
            'mobile', 'app', 'android', 'ios' => 'mobile',
            'agent', 'agent_app' => 'agent',
            'merchant', 'merchant_desk', 'counter' => 'merchant_desk',
            default => 'web',
        };
    }
}
