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
            'businessType' => 'bail|nullable|string|in:hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
            'business_type' => 'bail|nullable|string|in:hotel_ops,bus_ops,mixed,HOTEL,BUS_COMPANY,CONTRACT_MIDDLEMAN',
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

        $merchant = AgentReferredMerchant::create([
            'agent_id' => auth()->id(),
            'name' => $request->input('name'),
            'business_type' => $this->normalizeBusinessType(
                $request->input('businessType', $request->input('business_type', 'mixed'))
            ),
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
            'businessType' => 'bail|nullable|string|in:hotel_ops,bus_ops,mixed',
            'business_type' => 'bail|nullable|string|in:hotel_ops,bus_ops,mixed',
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

        $merchant->fill([
            'name' => $request->input('name', $merchant->name),
            'business_type' => $this->normalizeBusinessType(
                $request->input('businessType', $request->input('business_type', $merchant->business_type))
            ),
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
        if (! $merchant->isEditable() && $merchant->status !== AgentReferredMerchant::STATUS_SUBMITTED) {
            return response()->json(['success' => false, 'message' => __('Cannot upload documents now')], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'bail|required|string|in:nid,trade_license,contract,other',
            'document' => 'bail|required|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $disk = config('filesystems.profile_disk', 'public');
        $file = $request->file('document');
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk($disk)->putFileAs(
            'agents/referred-merchants/'.$merchant->id,
            $file,
            Str::uuid()->toString().'.'.$ext
        );

        $doc = AgentReferredMerchantDocument::create([
            'referred_merchant_id' => $merchant->id,
            'type' => $request->input('type'),
            'path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Document uploaded'),
            'data' => AgentApiPresenter::referredMerchantDocument($doc),
        ]);
    }

    private function owned(int $id): AgentReferredMerchant
    {
        return AgentReferredMerchant::query()
            ->where('agent_id', auth()->id())
            ->findOrFail($id);
    }

    private function normalizeBusinessType(?string $type): string
    {
        return match ($type) {
            'HOTEL', 'hotel_ops' => 'hotel_ops',
            'BUS_COMPANY', 'bus_ops' => 'bus_ops',
            default => 'mixed',
        };
    }
}
