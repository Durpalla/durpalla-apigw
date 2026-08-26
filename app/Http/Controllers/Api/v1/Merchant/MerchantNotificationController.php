<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class MerchantNotificationController extends Controller
{
    private function authActor(Request $request): Merchant|MerchantStaff
    {
        $u = $request->user();
        if ($u instanceof Merchant || $u instanceof MerchantStaff) {
            return $u;
        }

        abort(401);
    }

    public function index(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'unread_only' => 'nullable|boolean',
        ]);

        $perPage = (int) ($request->get('per_page', 30));
        $q = $u->notifications()->orderByDesc('created_at');
        if ($request->boolean('unread_only')) {
            $q->whereNull('read_at');
        }

        $rows = $q->paginate($perPage);
        $data = collect($rows->items())->map(fn (DatabaseNotification $n) => $this->map($n))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $u = $this->authActor($request);

        return response()->json([
            'success' => true,
            'data' => [
                'unread' => (int) $u->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $u = $this->authActor($request);
        /** @var DatabaseNotification|null $n */
        $n = $u->notifications()->where('id', $id)->first();
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }
        $n->markAsRead();

        return response()->json(['success' => true, 'message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $u->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    private function map(DatabaseNotification $n): array
    {
        $payload = is_array($n->data) ? $n->data : [];
        $title = $payload['title'] ?? $payload['subject'] ?? null;
        $body = $payload['body'] ?? $payload['message'] ?? null;

        return [
            'id' => (string) $n->id,
            'type' => (string) ($n->type ?? ''),
            'title' => $title !== null ? (string) $title : null,
            'body' => $body !== null ? (string) $body : null,
            'data' => $payload,
            'read_at' => $n->read_at?->format('c'),
            'created_at' => $n->created_at?->format('c'),
        ];
    }
}
