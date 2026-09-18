<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EntertainmentImage;
use App\Models\EntertainmentSlotTemplate;
use App\Models\EntertainmentTicketType;
use App\Models\EntertainmentVenue;
use App\Models\Merchant;
use App\Services\Entertainment\EntertainmentBookingService;
use App\Services\Entertainment\EntertainmentInventoryService;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MerchantEntertainmentController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly EntertainmentBookingService $booking,
        private readonly EntertainmentInventoryService $inventory,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $q = EntertainmentVenue::query()->where('merchant_id', $ownerId)->orderByDesc('id');
        if ($request->filled('search')) {
            $s = '%'.trim((string) $request->search).'%';
            $q->where(function ($inner) use ($s) {
                $inner->where('name', 'LIKE', $s)->orWhere('tagline', 'LIKE', $s);
            });
        }
        if ($request->filled('attraction_type')) {
            $q->where('attraction_type', (string) $request->attraction_type);
        }

        $paginator = $q->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $data = $this->validatedVenue($request);
        $data['merchant_id'] = $ownerId;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(4);
        $data['status'] = $data['status'] ?? 1;
        $data['theme_preset'] = $data['theme_preset'] ?? $data['attraction_type'];

        $venue = EntertainmentVenue::create($data);

        return response()->json(['success' => true, 'data' => $venue], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $venue->load(['ticketTypes', 'slotTemplates', 'images']);

        return response()->json(['success' => true, 'data' => $venue]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $venue->update($this->validatedVenue($request, false));

        return response()->json(['success' => true, 'data' => $venue->fresh()]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $request->validate(['status' => ['required', 'integer', 'in:0,1']]);
        $venue->update(['status' => (int) $request->status]);

        return response()->json(['success' => true, 'data' => $venue]);
    }

    public function upsertTicketType(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($data['id'])) {
            $type = EntertainmentTicketType::query()
                ->where('venue_id', $venue->id)
                ->whereKey((int) $data['id'])
                ->firstOrFail();
            $type->update($data);
        } else {
            $type = EntertainmentTicketType::create(array_merge($data, ['venue_id' => $venue->id]));
        }

        return response()->json(['success' => true, 'data' => $type]);
    }

    public function upsertSlot(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($data['id'])) {
            $slot = EntertainmentSlotTemplate::query()
                ->where('venue_id', $venue->id)
                ->whereKey((int) $data['id'])
                ->firstOrFail();
            $slot->update($data);
        } else {
            $slot = EntertainmentSlotTemplate::create(array_merge($data, ['venue_id' => $venue->id]));
        }

        return response()->json(['success' => true, 'data' => $slot]);
    }

    public function upsertInventory(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $data = $request->validate([
            'visit_date' => ['required', 'date'],
            'ticket_type_id' => ['nullable', 'integer'],
            'slot_template_id' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'stop_sale' => ['nullable', 'boolean'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $row = $this->inventory->upsertDay($venue, $data);

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function storeImage(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $venue = $this->ownedVenue($ownerId, $id);
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'in:cover,gallery,hero'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $image = EntertainmentImage::create([
            'venue_id' => $venue->id,
            'url' => $data['url'],
            'type' => $data['type'] ?? 'gallery',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }

    public function bookings(Request $request): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $q = Booking::query()
            ->entertainment()
            ->whereHas('entertainmentItems.venue', fn ($vq) => $vq->where('merchant_id', $ownerId))
            ->with(['entertainmentItems'])
            ->orderByDesc('id');

        $paginator = $q->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function walkIn(Request $request): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $data = $request->validate([
            'venue_id' => ['required', 'integer'],
            'visit_date' => ['nullable', 'date'],
            'slot_template_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ticket_type_id' => ['required', 'integer'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],
            'guest' => ['nullable', 'array'],
            'payment' => ['nullable', 'array'],
        ]);

        $this->ownedVenue($ownerId, (int) $data['venue_id']);
        $key = 'm-'.Str::uuid()->toString();
        $key = substr($key, 0, 64);

        try {
            $hold = $this->booking->createHold(null, $data, $key, null, $ownerId);
            $result = $this->booking->confirmFromHold(
                null,
                (int) $hold->id,
                'merchant_desk',
                $data['guest'] ?? null,
                null,
                $ownerId,
                array_merge(['mode' => 'full', 'method' => 'cash'], $data['payment'] ?? []),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $result['booking']->id,
                    'tickets' => array_map(fn ($t) => $this->booking->ticketPayload($t), $result['tickets']),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function lookupTicket(Request $request): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        $code = trim((string) ($request->query('code') ?: $request->input('code', '')));
        $ticket = $this->booking->lookupTicket($code);
        if (! $ticket || (int) ($ticket->venue?->merchant_id) !== $ownerId) {
            return response()->json(['success' => false, 'message' => __('Ticket not found')], 404);
        }

        return response()->json(['success' => true, 'data' => $this->booking->ticketPayload($ticket)]);
    }

    public function redeemTicket(Request $request, int $id): JsonResponse
    {
        return $this->doRedeem($request, $id, false);
    }

    public function redeemOverride(Request $request, int $id): JsonResponse
    {
        return $this->doRedeem($request, $id, true);
    }

    private function doRedeem(Request $request, int $id, bool $force): JsonResponse
    {
        $ownerId = $this->assertAllowed($request);
        if ($force) {
            $request->validate(['consent_note' => ['required', 'string', 'min:3', 'max:1000']]);
        }

        $ticket = $this->booking->lookupTicket((string) $id)
            ?? \App\Models\EntertainmentTicket::query()->with('venue')->find($id);

        if (! $ticket || (int) ($ticket->venue?->merchant_id) !== $ownerId) {
            // allow lookup by numeric id
            $ticket = \App\Models\EntertainmentTicket::query()->with('venue')->find($id);
            if (! $ticket || (int) ($ticket->venue?->merchant_id) !== $ownerId) {
                return response()->json(['success' => false, 'message' => __('Ticket not found')], 404);
            }
        }

        try {
            $actorId = (int) (Auth::id() ?: $ownerId);
            $updated = $this->booking->redeemTicket(
                $ticket,
                $actorId,
                'merchant_staff',
                $force,
                $force ? (string) $request->input('consent_note') : null,
            );

            return response()->json(['success' => true, 'data' => $this->booking->ticketPayload($updated)]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function validatedVenue(Request $request, bool $requireName = true): array
    {
        return $request->validate([
            'name' => [$requireName ? 'required' : 'sometimes', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'attraction_type' => ['nullable', 'string', 'in:park,museum,theme_park,zoo,kids,other'],
            'capacity_mode' => ['nullable', 'string', 'in:day,slot'],
            'city_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'highlights' => ['nullable', 'array'],
            'whats_included' => ['nullable', 'array'],
            'theme_preset' => ['nullable', 'string', 'max:32'],
            'accent' => ['nullable', 'string', 'max:32'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:50'],
            'default_daily_capacity' => ['nullable', 'integer', 'min:1'],
            'agent_commission_type' => ['nullable', 'string', 'in:percent,fixed'],
            'agent_commission' => ['nullable', 'numeric', 'min:0'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'cancellation_policy' => ['nullable', 'string'],
        ]);
    }

    private function ownedVenue(int $ownerId, int $id): EntertainmentVenue
    {
        return EntertainmentVenue::query()
            ->where('merchant_id', $ownerId)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function assertAllowed(Request $request): int
    {
        $ownerId = $this->merchantOwnerId($request);
        $merchant = Merchant::query()->find($ownerId);
        $allowed = [];
        if ($merchant !== null) {
            try {
                $raw = $merchant->allowed_service_types;
                $allowed = is_array($raw) ? $raw : [];
            } catch (\Throwable) {
                $allowed = [];
            }
        }
        $allowed = array_map(static fn ($t) => strtolower(trim((string) $t)), $allowed);
        if (count($allowed) > 0 && ! in_array('entertainment', $allowed, true)) {
            abort(response()->json([
                'success' => false,
                'message' => __('Entertainment is not enabled for this merchant.'),
            ], 403));
        }

        return $ownerId;
    }
}
