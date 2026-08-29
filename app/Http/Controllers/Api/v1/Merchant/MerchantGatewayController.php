<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Constants\GatewayConstant;
use App\Models\Gateway;
use App\Services\GatewayCatalogService;

/**
 * Merchant panel / app / front desk — manage own payment + SMS gateway rows
 * (clone from templates, edit credentials). No delete, no class_name change.
 */
class MerchantGatewayController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(private readonly GatewayCatalogService $catalog)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $type = $this->resolveTypeFilter($request);

        $owned = $this->catalog->listMerchantOwned($ownerId, $type);
        $templates = $this->catalog->listMerchantTemplates($type);
        $enabledCodes = $owned->pluck('code')->all();

        $data = [
            'gateways' => $owned->map(fn (Gateway $g) => $this->catalog->serializeGateway($g, true))->values(),
            'templates_available' => $templates
                ->reject(fn (Gateway $t) => in_array($t->code, $enabledCodes, true))
                ->map(fn (Gateway $t) => $this->catalog->serializeGateway($t))
                ->values(),
            'types' => GatewayConstant::merchantTypeLabels(),
        ];

        if ($type === null || $type === GatewayConstant::TYPE_PAYMENT) {
            $data['offline_desk'] = $this->catalog->listMerchantOfflineDesk()
                ->map(fn (Gateway $g) => $this->catalog->serializeGateway($g))
                ->values();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Templates for the "Gateway namespace" (class_name) dropdown.
     */
    public function templates(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);
        $type = $this->resolveTypeFilter($request);

        $rows = $this->catalog->listMerchantTemplates($type)->map(fn (Gateway $g) => [
            'id' => (int) $g->id,
            'name' => (string) $g->name,
            'code' => (string) $g->code,
            'type' => (string) $g->type,
            'class_name' => (string) $g->class_name,
            'label' => $g->name.' — '.$g->class_name,
            'setup' => $this->catalog->setupSchemaForGateway($g),
        ]);

        return response()->json(['success' => true, 'data' => $rows->values()]);
    }

    /**
     * Enable a listed template (clone). Body: gateway_id (template) — never client class_name.
     */
    public function enable(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $validator = Validator::make($request->all(), [
            'gateway_id' => ['required_without:code', 'nullable', 'integer'],
            'code' => ['required_without:gateway_id', 'nullable', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $template = null;
        if ($request->filled('gateway_id')) {
            $template = Gateway::query()->find((int) $request->input('gateway_id'));
        } elseif ($request->filled('code')) {
            $template = Gateway::query()
                ->whereNull('merchant_id')
                ->where('code', (string) $request->input('code'))
                ->first();
        }

        if (! $template) {
            return response()->json(['success' => false, 'message' => __('Gateway template not found')], 422);
        }

        try {
            $gateway = $this->catalog->enableForMerchant($ownerId, $template);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $hint = $gateway->type === GatewayConstant::TYPE_SMS
            ? __('Gateway enabled. Update SMS credentials to start sending messages.')
            : __('Gateway enabled. Update credentials to start accepting payments.');

        return response()->json([
            'success' => true,
            'message' => $hint,
            'data' => $this->catalog->serializeGateway($gateway, true),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $gateway = Gateway::query()->where('merchant_id', $ownerId)->where('id', $id)->first();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => __('Gateway not found')], 404);
        }

        if ($request->hasAny(['class_name', 'code', 'channel', 'merchant_id', 'type'])) {
            return response()->json([
                'success' => false,
                'message' => __('Gateway namespace, type, and channel cannot be changed.'),
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:0,1'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $gateway = $this->catalog->updateMerchantGateway($ownerId, $gateway, [
            'status' => (int) $request->input('status'),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Gateway updated'),
            'data' => $this->catalog->serializeGateway($gateway, true),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $gateway = Gateway::query()->where('merchant_id', $ownerId)->where('id', $id)->first();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => __('Gateway not found')], 404);
        }

        try {
            $this->catalog->removeMerchantGateway($ownerId, $gateway);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Gateway removed. You can enable it again from the template list.'),
        ]);
    }

    public function updateCredentials(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $gateway = Gateway::query()->where('merchant_id', $ownerId)->where('id', $id)->first();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => __('Gateway not found')], 404);
        }

        $validator = Validator::make($request->all(), [
            'credentials' => ['required', 'array', 'min:1'],
            'credentials.*' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $this->catalog->upsertMerchantCredentials($ownerId, $gateway, $request->input('credentials', []));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Credentials saved'),
            'data' => $this->catalog->serializeGateway($gateway->fresh(), true),
        ]);
    }

    public function updateParams(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $gateway = Gateway::query()->where('merchant_id', $ownerId)->where('id', $id)->first();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => __('Gateway not found')], 404);
        }

        $validator = Validator::make($request->all(), [
            'params' => ['required', 'array'],
            'params.*' => ['nullable', 'string'],
            'sync' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $uid = (int) ($request->user()->id ?? 0) ?: null;
            $sync = $request->boolean('sync', true);
            $this->catalog->upsertMerchantParams($ownerId, $gateway, $request->input('params', []), $uid, $sync);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Params saved'),
            'data' => $this->catalog->serializeGateway($gateway->fresh(), true),
        ]);
    }

    private function resolveTypeFilter(Request $request): ?string
    {
        $type = $request->query('type', $request->input('type'));
        if ($type === null || $type === '' || $type === 'all') {
            return null;
        }

        Validator::make(['type' => $type], [
            'type' => ['required', Rule::in(GatewayConstant::merchantManageableTypes())],
        ])->validate();

        return (string) $type;
    }
}
