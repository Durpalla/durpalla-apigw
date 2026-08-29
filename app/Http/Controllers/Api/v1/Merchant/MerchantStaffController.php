<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\MerchantStaff;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleSupervisor;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Merchant Desk Pro — staff tied to this merchant, with vehicle assignments for supervisors.
 */
class MerchantStaffController extends Controller
{
    use ResolvesMerchantOwner;

    /**
     * Staff type values stored on merchant_staff.type.
     */
    private const MERCHANT_ASSIGNABLE_TYPES = [
        'manager',
        'supervisor',
        'driver',
        'assistant',
        'counter',
        'executive',
        'viewer',
        'accountant',
    ];

    /**
     * Desk section permissions merchants may assign directly to staff.
     */
    private const DESK_PERMISSIONS = [
        'dashboard',
        'properties',
        'hotels',
        'routes',
        'trips',
        'bookings',
        'reports',
        'settlements',
        'staff',
        'settings',
        'live_bookings',
        'notifications',
        'support',
    ];

    /**
     * List staff where merchant_staff.merchant_id is this merchant owner id.
     *
     * Query: search, type (optional), per_page (max 200)
     */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $query = MerchantStaff::query()
            ->with(['vehicles.vehicle:id,name'])
            ->where('merchant_id', $ownerId);

        $typeFilter = $request->input('type', $request->input('role'));
        if (filled($typeFilter) && $typeFilter !== 'all') {
            $query->where('type', (string) $typeFilter);
        } else {
            $query->whereIn('type', self::MERCHANT_ASSIGNABLE_TYPES);
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', $s)
                    ->orWhere('mobile', 'LIKE', $s)
                    ->orWhere('email', 'LIKE', $s);
            });
        }

        $limit = min(max((int) $request->get('per_page', 100), 1), 200);
        $users = $query->orderBy('name')->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(fn (MerchantStaff $u) => $this->transformStaffUser($u)),
        ]);
    }

    /**
     * GET /merchant/staff/roles — staff types a merchant may assign (legacy route name).
     */
    public function rolesForCreate(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);

        $data = collect(self::MERCHANT_ASSIGNABLE_TYPES)
            ->values()
            ->map(fn (string $name, int $i) => [
                'id' => (string) ($i + 1),
                'name' => $name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /merchant/staff/permissions — desk permissions that may be granted directly.
     */
    public function permissionOptions(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);

        $data = collect(self::DESK_PERMISSIONS)
            ->values()
            ->map(fn (string $name, int $i) => [
                'id' => (string) ($i + 1),
                'name' => $name,
                'label' => str_replace('_', ' ', ucwords($name, '_')),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /merchant/staff — create staff (type + direct permissions).
     */
    public function store(Request $request): JsonResponse
    {
        $this->assertMainMerchant($request);
        $ownerId = $this->merchantOwnerId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', 'unique:merchant_staff,email'],
            'mobile' => ['required', 'string', 'max:20', 'unique:merchant_staff,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'type' => ['required_without:role', 'nullable', 'string', Rule::in(self::MERCHANT_ASSIGNABLE_TYPES)],
            'role' => ['required_without:type', 'nullable', 'string', Rule::in(self::MERCHANT_ASSIGNABLE_TYPES)],
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['string', Rule::in(self::DESK_PERMISSIONS)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(self::DESK_PERMISSIONS)],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'min:1'],
        ]);

        $typeName = (string) ($validated['type'] ?? $validated['role']);
        $permissions = $this->normalizePermissionNames($validated);

        try {
            $user = DB::transaction(function () use ($request, $validated, $ownerId, $typeName, $permissions) {
                $user = new MerchantStaff;
                $user->merchant_id = $ownerId;
                $user->name = $validated['name'];
                $user->email = ! empty($validated['email']) ? $validated['email'] : null;
                $user->mobile = $validated['mobile'];
                $user->password = $validated['password'];
                $user->type = $typeName;
                $user->permissions = $permissions;
                $user->status = 1;
                if ($user->email) {
                    $user->email_verified_at = now();
                }
                $user->save();

                if ($typeName === AppConst::SUPERVISOR_ROLE && ! empty($validated['vehicle_ids'])) {
                    $this->replaceSupervisorVehicles(
                        $ownerId,
                        (int) $user->id,
                        $validated['vehicle_ids'],
                        $request->user()?->id
                    );
                }

                $user->load(['vehicles.vehicle:id,name']);

                return $user;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff created.',
            'data' => $this->transformStaffUser($user),
        ], 201);
    }

    private function scopedSupervisor(int $ownerId, int $id): MerchantStaff
    {
        return MerchantStaff::query()
            ->with(['vehicles.vehicle:id,name'])
            ->where('merchant_id', $ownerId)
            ->where('type', AppConst::SUPERVISOR_ROLE)
            ->findOrFail($id);
    }

    private function scopedStaff(int $ownerId, int $id): MerchantStaff
    {
        return MerchantStaff::query()
            ->with(['vehicles.vehicle:id,name'])
            ->where('merchant_id', $ownerId)
            ->whereIn('type', self::MERCHANT_ASSIGNABLE_TYPES)
            ->findOrFail($id);
    }

    /**
     * GET /merchant/staff/{id} — single staff detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $u = $this->scopedStaff($ownerId, $id);

        return response()->json([
            'success' => true,
            'data' => $this->transformStaffUser($u),
        ]);
    }

    /**
     * GET /merchant/staff/{id}/summary — brief stats for staff details screen.
     */
    public function summary(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $u = $this->scopedStaff($ownerId, $id);

        $vehicleIds = $u->vehicles ? $u->vehicles->pluck('vehicle_id')->unique()->values()->all() : [];
        $today = now()->toDateString();

        $tripsToday = $vehicleIds === []
            ? 0
            : (int) VehicleSchedule::whereIn('vehicle_id', $vehicleIds)
                ->whereDate('schedule_date', $today)
                ->count();

        $upcomingTrips = $vehicleIds === []
            ? 0
            : (int) VehicleSchedule::whereIn('vehicle_id', $vehicleIds)
                ->whereDate('schedule_date', '>=', $today)
                ->where('status', AppConst::SCHEDULE_ACTIVE)
                ->count();

        $bookings7d = (int) Booking::where('booked_by_id', $u->id)
            ->where('booked_by_type', MerchantStaff::class)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $bookingsToday = (int) Booking::where('booked_by_id', $u->id)
            ->where('booked_by_type', MerchantStaff::class)
            ->whereDate('created_at', $today)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'staff_id' => (string) $u->id,
                'assigned_vehicles' => count($vehicleIds),
                'trips_today' => $tripsToday,
                'upcoming_trips' => $upcomingTrips,
                'bookings_today' => $bookingsToday,
                'bookings_last_7_days' => $bookings7d,
            ],
        ]);
    }

    /**
     * GET /merchant/staff/{id}/activities — recent activity logs (brief).
     */
    public function activities(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $u = $this->scopedStaff($ownerId, $id);

        $limit = min(max((int) $request->get('limit', 20), 1), 50);
        if (! \Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $rows = ActivityLog::where('user_id', $u->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'type', 'subject', 'url', 'method', 'ip', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($r) => [
                'id' => (string) $r->id,
                'type' => (string) ($r->type ?? 'info'),
                'subject' => (string) ($r->subject ?? ''),
                'method' => (string) ($r->method ?? ''),
                'url' => (string) ($r->url ?? ''),
                'ip' => (string) ($r->ip ?? ''),
                'created_at' => $r->created_at ? $r->created_at->format('c') : null,
            ])->values(),
        ]);
    }

    /**
     * PATCH /merchant/staff/{id} — update staff profile, type, and direct permissions.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertMainMerchant($request);
        $ownerId = $this->merchantOwnerId($request);

        $u = MerchantStaff::query()
            ->with(['vehicles.vehicle:id,name'])
            ->where('merchant_id', $ownerId)
            ->where('id', $id)
            ->whereIn('type', self::MERCHANT_ASSIGNABLE_TYPES)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('merchant_staff', 'email')->ignore($u->id)],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('merchant_staff', 'mobile')->ignore($u->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'type' => ['nullable', 'string', Rule::in(self::MERCHANT_ASSIGNABLE_TYPES)],
            'role' => ['nullable', 'string', Rule::in(self::MERCHANT_ASSIGNABLE_TYPES)],
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['string', Rule::in(self::DESK_PERMISSIONS)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(self::DESK_PERMISSIONS)],
        ]);

        $hadSupervisorType = $u->type === AppConst::SUPERVISOR_ROLE;
        $nextType = (string) ($validated['type'] ?? $validated['role'] ?? $u->type);
        $hasPermissionPayload = array_key_exists('permission_names', $validated)
            || array_key_exists('permissions', $validated);

        try {
            DB::transaction(function () use ($validated, $u, $hadSupervisorType, $nextType, $hasPermissionPayload) {
                $u->name = $validated['name'];
                $u->mobile = $validated['mobile'];
                if (array_key_exists('email', $validated)) {
                    $u->email = filled($validated['email']) ? (string) $validated['email'] : null;
                    if ($u->email) {
                        $u->email_verified_at = $u->email_verified_at ?? now();
                    } else {
                        $u->email_verified_at = null;
                    }
                }
                if (! empty($validated['password'])) {
                    $u->password = $validated['password'];
                }

                $u->type = $nextType;
                if ($nextType !== AppConst::SUPERVISOR_ROLE && $hadSupervisorType) {
                    VehicleSupervisor::where('supervisor_id', $u->id)->delete();
                }

                if ($hasPermissionPayload) {
                    $u->permissions = $this->normalizePermissionNames($validated);
                }

                $u->save();
                $u->load(['vehicles.vehicle:id,name']);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $u->refresh();
        $u->load(['vehicles.vehicle:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Staff updated.',
            'data' => $this->transformStaffUser($u),
        ]);
    }

    /**
     * PATCH /merchant/staff/{id}/status {is_active:boolean}
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->assertMainMerchant($request);
        $ownerId = $this->merchantOwnerId($request);
        $u = MerchantStaff::query()
            ->where('merchant_id', $ownerId)
            ->where('id', $id)
            ->whereIn('type', self::MERCHANT_ASSIGNABLE_TYPES)
            ->firstOrFail();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $u->status = $validated['is_active'] ? 1 : 0;
        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data' => [
                'id' => (string) $u->id,
                'status' => (int) $u->status,
            ],
        ]);
    }

    /**
     * PUT /merchant/staff/{id}/vehicles {vehicle_ids: int[]}
     *
     * Only main merchants can assign vehicles they own. supervisor_id = merchant_staff.id.
     */
    public function assignVehicles(Request $request, int $id): JsonResponse
    {
        $this->assertMainMerchant($request);
        $ownerId = $this->merchantOwnerId($request);
        $u = $this->scopedSupervisor($ownerId, $id);

        $validated = $request->validate([
            'vehicle_ids' => ['required', 'array'],
            'vehicle_ids.*' => ['integer', 'min:1'],
        ]);

        $vehicleIds = collect($validated['vehicle_ids'])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($vehicleIds !== []) {
            $owned = Vehicle::query()
                ->where('merchant_id', $ownerId)
                ->whereIn('id', $vehicleIds)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();

            if (count($owned) !== count($vehicleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more vehicles are not owned by this merchant.',
                ], 422);
            }
        }

        VehicleSupervisor::where('supervisor_id', $u->id)->delete();
        foreach ($vehicleIds as $vid) {
            VehicleSupervisor::create([
                'vehicle_id' => $vid,
                'supervisor_id' => $u->id,
                'user_id' => $request->user()?->id,
            ]);
        }

        $u->load(['vehicles.vehicle:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Vehicles assigned.',
            'data' => [
                'id' => (string) $u->id,
                'vehicles' => $u->vehicles->map(fn ($vs) => [
                    'id' => (string) $vs->vehicle_id,
                    'name' => $vs->vehicle?->name ?? '',
                ])->values(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function normalizePermissionNames(array $validated): array
    {
        $raw = $validated['permission_names'] ?? $validated['permissions'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $raw,
        ), static fn ($value) => in_array($value, self::DESK_PERMISSIONS, true))));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformStaffUser(MerchantStaff $u): array
    {
        $type = (string) ($u->type ?? '');
        $permissions = $u->permissionNames();

        return [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email ?? '',
            'mobile' => $u->mobile ?? '',
            'status' => (int) $u->status,
            'type' => $type,
            'merchant_id' => (string) ($u->merchant_id ?? ''),
            'permissions' => $permissions,
            'direct_permissions' => $permissions,
            // Legacy compatibility for older desk clients.
            'roles' => $type !== '' ? [$type] : [],
            'primary_role' => $type,
            'vehicles' => $u->relationLoaded('vehicles')
                ? $u->vehicles->map(fn ($vs) => [
                    'id' => (string) $vs->vehicle_id,
                    'name' => $vs->vehicle?->name ?? '',
                ])->values()
                : [],
        ];
    }

    /**
     * @param  array<int, int>  $vehicleIds
     */
    private function replaceSupervisorVehicles(int $ownerId, int $supervisorId, array $vehicleIds, ?int $actorId): void
    {
        $vehicleIds = collect($vehicleIds)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($vehicleIds === []) {
            return;
        }

        $owned = Vehicle::query()
            ->where('merchant_id', $ownerId)
            ->whereIn('id', $vehicleIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (count($owned) !== count($vehicleIds)) {
            throw ValidationException::withMessages([
                'vehicle_ids' => ['One or more vehicles are not owned by this merchant.'],
            ]);
        }

        VehicleSupervisor::where('supervisor_id', $supervisorId)->delete();
        foreach ($vehicleIds as $vid) {
            VehicleSupervisor::create([
                'vehicle_id' => $vid,
                'supervisor_id' => $supervisorId,
                'user_id' => $actorId,
            ]);
        }
    }
}
