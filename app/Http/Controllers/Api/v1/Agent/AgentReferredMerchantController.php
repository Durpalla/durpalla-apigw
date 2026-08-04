<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentReferredMerchant;
use App\Models\AgentReferredMerchantDocument;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgentReferredMerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requestedPage = (int) $request->get('page', 0);
        $page = $requestedPage <= 0 ? 1 : $requestedPage + 1;
        $size = max(1, min(100, (int) $request->get('size', 50)));

        $paginator = AgentReferredMerchant::query()
            ->withCount('documents')
            ->with(['documents' => static function ($query) {
                $query->where('type', 'logo')->orderByDesc('id');
            }])
            ->where('agent_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'items' => collect($paginator->items())
                    ->map(fn (AgentReferredMerchant $m) => AgentApiPresenter::referredMerchant($m))
                    ->values()
                    ->all(),
                'page' => max(0, $paginator->currentPage() - 1),
                'size' => $paginator->perPage(),
                'totalElements' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $merchant = $this->owned($id);
        $merchant->load('documents');

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => AgentApiPresenter::referredMerchant($merchant, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|max:191',
            'businessType' => 'bail|nullable|string|max:128',
            'business_type' => 'bail|nullable|string|max:128',
            'businessTypes' => 'bail|nullable|array|min:1',
            'businessTypes.*' => 'bail|string|in:hotel,bus,train,air,launch,hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
            'business_types' => 'bail|nullable|array|min:1',
            'business_types.*' => 'bail|string|in:hotel,bus,train,air,launch,hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
            'contactPerson' => 'bail|required_without:contact_person|string|max:191',
            'contact_person' => 'bail|required_without:contactPerson|string|max:191',
            'contactMobile' => 'bail|required_without:contact_mobile|string|max:20',
            'contact_mobile' => 'bail|required_without:contactMobile|string|max:20',
            'address' => 'bail|nullable|string|max:500',
            'city' => 'bail|nullable|string|max:191',
            'tradeLicenseNo' => 'bail|nullable|string|max:191',
            'trade_license_no' => 'bail|nullable|string|max:191',
            'notes' => 'bail|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $businessType = $this->resolveBusinessTypes($request);
        if ($businessType === '') {
            return response()->json(['success' => false, 'message' => __('Select at least one property type')], 422);
        }

        $merchant = AgentReferredMerchant::create([
            'agent_id' => auth()->id(),
            'name' => $request->input('name'),
            'business_type' => $businessType,
            'contact_person' => $request->input('contactPerson', $request->input('contact_person')),
            'contact_mobile' => $request->input('contactMobile', $request->input('contact_mobile')),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'trade_license_no' => $request->input('tradeLicenseNo', $request->input('trade_license_no')),
            'notes' => $request->input('notes'),
            'status' => AgentReferredMerchant::STATUS_LEAD,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Merchant referred successfully'),
            'data' => AgentApiPresenter::referredMerchant($merchant),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $merchant = $this->owned($id);
        if (! $merchant->isEditable()) {
            return response()->json(['success' => false, 'message' => __('Cannot edit this referral')], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'bail|sometimes|required|string|max:191',
            'businessType' => 'bail|nullable|string|max:128',
            'business_type' => 'bail|nullable|string|max:128',
            'businessTypes' => 'bail|nullable|array|min:1',
            'businessTypes.*' => 'bail|string|in:hotel,bus,train,air,launch,hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
            'business_types' => 'bail|nullable|array|min:1',
            'business_types.*' => 'bail|string|in:hotel,bus,train,air,launch,hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
            'contactPerson' => 'bail|nullable|string|max:191',
            'contact_person' => 'bail|nullable|string|max:191',
            'contactMobile' => 'bail|nullable|string|max:20',
            'contact_mobile' => 'bail|nullable|string|max:20',
            'address' => 'bail|nullable|string|max:500',
            'city' => 'bail|nullable|string|max:191',
            'tradeLicenseNo' => 'bail|nullable|string|max:191',
            'trade_license_no' => 'bail|nullable|string|max:191',
            'notes' => 'bail|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $businessType = $merchant->business_type;
        if ($request->hasAny(['businessTypes', 'business_types', 'businessType', 'business_type'])) {
            $resolved = $this->resolveBusinessTypes($request);
            if ($resolved === '') {
                return response()->json(['success' => false, 'message' => __('Select at least one property type')], 422);
            }
            $businessType = $resolved;
        }

        $merchant->fill([
            'name' => $request->input('name', $merchant->name),
            'business_type' => $businessType,
            'contact_person' => $request->input('contactPerson', $request->input('contact_person', $merchant->contact_person)),
            'contact_mobile' => $request->input('contactMobile', $request->input('contact_mobile', $merchant->contact_mobile)),
            'address' => $request->input('address', $merchant->address),
            'city' => $request->input('city', $merchant->city),
            'trade_license_no' => $request->input('tradeLicenseNo', $request->input('trade_license_no', $merchant->trade_license_no)),
            'notes' => $request->input('notes', $merchant->notes),
        ]);
        if ($merchant->status === AgentReferredMerchant::STATUS_REJECTED) {
            $merchant->status = AgentReferredMerchant::STATUS_LEAD;
            $merchant->reject_reason = null;
        }
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => __('Updated'),
            'data' => AgentApiPresenter::referredMerchant($merchant->fresh('documents'), true),
        ]);
    }

    public function submit(int $id): JsonResponse
    {
        $merchant = $this->owned($id);
        if (! in_array($merchant->status, [AgentReferredMerchant::STATUS_LEAD, AgentReferredMerchant::STATUS_REJECTED], true)) {
            return response()->json(['success' => false, 'message' => __('Already submitted')], 422);
        }

        $merchant->status = AgentReferredMerchant::STATUS_SUBMITTED;
        $merchant->reject_reason = null;
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => __('Submitted for review'),
            'data' => AgentApiPresenter::referredMerchant($merchant->fresh('documents'), true),
        ]);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $merchant = $this->owned($id);
        if (! in_array($merchant->status, [
            AgentReferredMerchant::STATUS_LEAD,
            AgentReferredMerchant::STATUS_REJECTED,
        ], true)) {
            return response()->json(['success' => false, 'message' => __('Cannot upload documents now')], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'bail|required|string|in:logo,nid,trade_license,contract,other',
            'document' => 'bail|required|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $type = (string) $request->input('type');
        $disk = config('filesystems.profile_disk', 'public');
        $file = $request->file('document');
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk($disk)->putFileAs(
            'agents/referred-merchants/'.$merchant->id,
            $file,
            Str::uuid()->toString().'.'.$ext
        );

        $existing = AgentReferredMerchantDocument::query()
            ->where('referred_merchant_id', $merchant->id)
            ->where('type', $type)
            ->orderByDesc('id')
            ->get();
        foreach ($existing as $old) {
            if (! empty($old->path)) {
                Storage::disk($disk)->delete($old->path);
            }
            $old->delete();
        }

        AgentReferredMerchantDocument::create([
            'referred_merchant_id' => $merchant->id,
            'type' => $type,
            'path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Document uploaded'),
            'data' => AgentApiPresenter::referredMerchant($merchant->fresh('documents'), true),
        ]);
    }

    private function owned(int $id): AgentReferredMerchant
    {
        return AgentReferredMerchant::query()
            ->where('agent_id', auth()->id())
            ->findOrFail($id);
    }

    private function resolveBusinessTypes(Request $request): string
    {
        $raw = $request->input('businessTypes', $request->input('business_types'));
        if (! is_array($raw) || $raw === []) {
            $single = $request->input('businessType', $request->input('business_type'));
            if (is_string($single) && str_contains($single, ',')) {
                $raw = preg_split('/\s*,\s*/', $single) ?: [];
            } elseif ($single !== null && $single !== '') {
                $raw = [$single];
            } else {
                $raw = [];
            }
        }

        $normalized = [];
        foreach ($raw as $type) {
            $code = $this->normalizeBusinessType(is_string($type) ? $type : null);
            if ($code !== '' && ! in_array($code, $normalized, true)) {
                $normalized[] = $code;
            }
        }

        return implode(',', $normalized);
    }

    private function normalizeBusinessType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'hotel', 'hotel_ops' => 'hotel',
            'bus', 'bus_company', 'bus_ops' => 'bus',
            'train' => 'train',
            'air', 'airline', 'flight' => 'air',
            'launch' => 'launch',
            'mixed', 'contract_middleman', 'partner' => 'mixed',
            default => '',
        };
    }
}
