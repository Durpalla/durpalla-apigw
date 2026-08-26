<?php

namespace App\Services\Support;

use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;

class SupportTicketService
{
    public function normalizeStatus(?string $status): ?string
    {
        if ($status === null || $status === '' || strcasecmp($status, 'All') === 0) {
            return null;
        }

        $map = [
            'open' => SupportTicket::STATUS_OPEN,
            'in progress' => SupportTicket::STATUS_IN_PROGRESS,
            'in_progress' => SupportTicket::STATUS_IN_PROGRESS,
            'on hold' => SupportTicket::STATUS_ON_HOLD,
            'on_hold' => SupportTicket::STATUS_ON_HOLD,
            'resolved' => SupportTicket::STATUS_RESOLVED,
            'closed' => SupportTicket::STATUS_CLOSED,
        ];

        $key = strtolower(trim($status));

        return $map[$key] ?? (in_array($key, config('support.statuses', []), true) ? $key : null);
    }

    public function normalizePriority(?string $priority): ?string
    {
        if ($priority === null || $priority === '' || strcasecmp($priority, 'All') === 0) {
            return null;
        }

        $key = strtolower(trim($priority));

        return in_array($key, config('support.priorities', []), true) ? $key : null;
    }

    public function counts(?int $merchantId = null): array
    {
        $query = SupportTicket::query();
        if ($merchantId) {
            $query->forMerchant($merchantId);
        }

        $rows = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $rows->sum(),
            'open' => (int) ($rows[SupportTicket::STATUS_OPEN] ?? 0),
            'progress' => (int) ($rows[SupportTicket::STATUS_IN_PROGRESS] ?? 0),
            'on_hold' => (int) ($rows[SupportTicket::STATUS_ON_HOLD] ?? 0),
            'resolved' => (int) ($rows[SupportTicket::STATUS_RESOLVED] ?? 0),
            'closed' => (int) ($rows[SupportTicket::STATUS_CLOSED] ?? 0),
        ];
    }

    public function list(array $filters = [], ?int $merchantId = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = SupportTicket::query()->withCount(['replies', 'attachments'])->latest();

        if ($merchantId) {
            $query->forMerchant($merchantId);
        }

        if ($status = $this->normalizeStatus($filters['status'] ?? null)) {
            $query->where('status', $status);
        }

        if ($priority = $this->normalizePriority($filters['priority'] ?? null)) {
            $query->where('priority', $priority);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', '%' . $term . '%')
                    ->orWhere('subject', 'like', '%' . $term . '%')
                    ->orWhere('contact_name', 'like', '%' . $term . '%')
                    ->orWhere('contact_email', 'like', '%' . $term . '%')
                    ->orWhere('contact_phone', 'like', '%' . $term . '%');
            });
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function findForMerchant(string $idOrNumber, int $merchantId): SupportTicket
    {
        return SupportTicket::query()
            ->with(['replies.attachments', 'attachments'])
            ->forMerchant($merchantId)
            ->where(function ($q) use ($idOrNumber) {
                $q->where('ticket_number', $idOrNumber);
                if (ctype_digit($idOrNumber)) {
                    $q->orWhere('id', (int) $idOrNumber);
                }
            })
            ->firstOrFail();
    }

    public function find(string $idOrNumber): SupportTicket
    {
        return SupportTicket::query()
            ->with(['replies.attachments', 'attachments', 'merchant'])
            ->where(function ($q) use ($idOrNumber) {
                $q->where('ticket_number', $idOrNumber);
                if (ctype_digit($idOrNumber)) {
                    $q->orWhere('id', (int) $idOrNumber);
                }
            })
            ->firstOrFail();
    }

    public function createForMerchant(
        int $merchantId,
        Model $actor,
        array $data,
        array $files = []
    ): SupportTicket {
        return DB::transaction(function () use ($merchantId, $actor, $data, $files) {
            $contact = $this->resolveContact($actor, $data);

            $ticket = SupportTicket::create([
                'ticket_number' => $this->nextTicketNumber(),
                'merchant_id' => $merchantId,
                'created_by_type' => $actor->getMorphClass(),
                'created_by_id' => $actor->getKey(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $data['category'] ?? 'General',
                'priority' => $this->normalizePriority($data['priority'] ?? 'medium') ?? SupportTicket::PRIORITY_MEDIUM,
                'status' => SupportTicket::STATUS_OPEN,
                'contact_name' => $contact['name'],
                'contact_email' => $contact['email'],
                'contact_phone' => $contact['phone'],
            ]);

            $this->storeAttachments($ticket, $files);

            return $ticket->fresh(['attachments', 'replies']);
        });
    }

    public function addReply(
        SupportTicket $ticket,
        Model $actor,
        string $body,
        bool $isStaff = false,
        array $files = []
    ): SupportTicketReply {
        return DB::transaction(function () use ($ticket, $actor, $body, $isStaff, $files) {
            $reply = SupportTicketReply::create([
                'support_ticket_id' => $ticket->id,
                'author_type' => $actor->getMorphClass(),
                'author_id' => $actor->getKey(),
                'author_name' => $this->actorDisplayName($actor),
                'is_staff' => $isStaff,
                'body' => $body,
            ]);

            $this->storeAttachments($ticket, $files, $reply->id);

            if ($isStaff && $ticket->status === SupportTicket::STATUS_OPEN) {
                $ticket->status = SupportTicket::STATUS_IN_PROGRESS;
                $ticket->save();
            } else {
                $ticket->touch();
            }

            return $reply->fresh('attachments');
        });
    }

    public function updateStatus(SupportTicket $ticket, string $status, ?User $assignee = null): SupportTicket
    {
        $normalized = $this->normalizeStatus($status);
        if (! $normalized) {
            abort(422, 'Invalid status.');
        }

        $ticket->status = $normalized;
        if (in_array($normalized, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $ticket->closed_at = now();
        } elseif ($ticket->closed_at) {
            $ticket->closed_at = null;
        }

        if ($assignee) {
            $ticket->assigned_to = $assignee->id;
        }

        $ticket->save();

        return $ticket->fresh(['attachments', 'replies.attachments']);
    }

    public function present(SupportTicket $ticket, bool $withDetails = false): array
    {
        $payload = [
            'id' => $ticket->ticket_number,
            'numericId' => $ticket->id,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'category' => $ticket->category,
            'priority' => $ticket->priority_label,
            'status' => $ticket->status_label,
            'contactName' => $ticket->contact_name ?? '',
            'contactEmail' => $ticket->contact_email ?? '',
            'contactPhone' => $ticket->contact_phone ?? '',
            'createdAt' => optional($ticket->created_at)?->toIso8601String(),
            'updatedAt' => optional($ticket->updated_at)?->toIso8601String(),
            'createdAtHuman' => $this->humanDiff($ticket->created_at),
            'updatedAtHuman' => $this->humanDiff($ticket->updated_at),
            'replyCount' => $ticket->replies_count ?? $ticket->replies()->count(),
            'attachmentCount' => $ticket->attachments_count ?? $ticket->attachments()->count(),
        ];

        if ($withDetails) {
            $payload['attachments'] = $ticket->attachments
                ->whereNull('reply_id')
                ->values()
                ->map(fn (SupportTicketAttachment $a) => $this->presentAttachment($a))
                ->all();

            $payload['replies'] = $ticket->replies->map(function (SupportTicketReply $reply) {
                return [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'authorName' => $reply->author_name,
                    'isStaff' => (bool) $reply->is_staff,
                    'createdAt' => optional($reply->created_at)?->toIso8601String(),
                    'createdAtHuman' => $this->humanDiff($reply->created_at),
                    'attachments' => $reply->attachments->map(fn (SupportTicketAttachment $a) => $this->presentAttachment($a))->all(),
                ];
            })->all();
        }

        return $payload;
    }

    public function presentAttachment(SupportTicketAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'fileName' => $attachment->file_name,
            'mimeType' => $attachment->mime_type,
            'fileSize' => $attachment->file_size,
            'url' => $attachment->url,
        ];
    }

    public function nextTicketNumber(): string
    {
        $prefix = config('support.ticket_prefix', 'TK');
        $year = date('Y');
        $like = $prefix . '-' . $year . '-%';

        $latest = SupportTicket::query()
            ->where('ticket_number', 'like', $like)
            ->orderByDesc('id')
            ->value('ticket_number');

        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function storeAttachments(SupportTicket $ticket, array $files, ?int $replyId = null): void
    {
        $max = (int) config('support.max_attachments', 3);
        $files = array_slice(array_values(array_filter($files)), 0, $max);
        if (! $files) {
            return;
        }

        $disk = config('support.attachment_disk', 'public');
        $base = trim((string) config('support.attachment_path', 'support-tickets'), '/');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store($base . '/' . $ticket->id, $disk);
            SupportTicketAttachment::create([
                'support_ticket_id' => $ticket->id,
                'reply_id' => $replyId,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
            ]);
        }
    }

    protected function resolveContact(Model $actor, array $data): array
    {
        if ($actor instanceof Merchant) {
            return [
                'name' => $data['contact_name'] ?? $actor->merchant_name ?? 'Merchant',
                'email' => $data['contact_email'] ?? $actor->merchant_email ?? '',
                'phone' => $data['contact_phone'] ?? $actor->merchant_mobile ?? $actor->merchant_phone ?? '',
            ];
        }

        if ($actor instanceof MerchantStaff) {
            return [
                'name' => $data['contact_name'] ?? $actor->name ?? 'Merchant Staff',
                'email' => $data['contact_email'] ?? $actor->email ?? '',
                'phone' => $data['contact_phone'] ?? $actor->mobile ?? '',
            ];
        }

        return [
            'name' => $data['contact_name'] ?? ($actor->name ?? 'User'),
            'email' => $data['contact_email'] ?? ($actor->email ?? ''),
            'phone' => $data['contact_phone'] ?? '',
        ];
    }

    protected function actorDisplayName(Model $actor): string
    {
        if ($actor instanceof Merchant) {
            return (string) ($actor->merchant_name ?: 'Merchant');
        }

        return (string) ($actor->name ?? class_basename($actor));
    }

    protected function humanDiff($time): string
    {
        if (! $time) {
            return '';
        }

        $carbon = $time instanceof Carbon ? $time : Carbon::parse($time);

        return $carbon->diffForHumans();
    }
}
