<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\Support\SupportTicketService;

class MerchantSupportTicketController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(private SupportTicketService $tickets)
    {
    }

    public function counts(Request $request): JsonResponse
    {
        $merchantId = $this->merchantOwnerId($request);

        return response()->json([
            'success' => true,
            'data' => $this->tickets->counts($merchantId),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $merchantId = $this->merchantOwnerId($request);
        $perPage = (int) $request->input('per_page', 20);

        $paginator = $this->tickets->list([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'category' => $request->input('category'),
        ], $merchantId, $perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn ($t) => $this->tickets->present($t))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $this->tickets->counts($merchantId),
        ]);
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $merchantId = $this->merchantOwnerId($request);
        $model = $this->tickets->findForMerchant($ticket, $merchantId);

        return response()->json([
            'success' => true,
            'data' => $this->tickets->present($model, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $merchantId = $this->merchantOwnerId($request);
        $actor = $this->authActor($request);

        $categories = config('support.categories', ['General']);
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'category' => ['required', 'string', Rule::in($categories)],
            'priority' => ['required', 'string', Rule::in(['Low', 'Medium', 'High', 'low', 'medium', 'high'])],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'attachments' => ['nullable', 'array', 'max:' . (int) config('support.max_attachments', 3)],
            'attachments.*' => [
                'file',
                'max:' . (int) config('support.max_attachment_kb', 5120),
            ],
        ]);

        $files = $request->file('attachments', []) ?: [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $ticket = $this->tickets->createForMerchant($merchantId, $actor, $validated, $files);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created.',
            'data' => $this->tickets->present($ticket->load(['attachments', 'replies']), true),
        ], 201);
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $merchantId = $this->merchantOwnerId($request);
        $actor = $this->authActor($request);
        $model = $this->tickets->findForMerchant($ticket, $merchantId);

        if (in_array($model->status, ['closed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is closed.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:' . (int) config('support.max_attachments', 3)],
            'attachments.*' => [
                'file',
                'max:' . (int) config('support.max_attachment_kb', 5120),
            ],
        ]);

        $files = $request->file('attachments', []) ?: [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $this->tickets->addReply($model, $actor, $validated['body'], false, $files);

        return response()->json([
            'success' => true,
            'message' => 'Reply added.',
            'data' => $this->tickets->present($model->fresh(['attachments', 'replies.attachments']), true),
        ]);
    }

    private function authActor(Request $request): Merchant|MerchantStaff
    {
        $user = $request->user();
        if ($user instanceof Merchant || $user instanceof MerchantStaff) {
            return $user;
        }

        abort(401);
    }
}
