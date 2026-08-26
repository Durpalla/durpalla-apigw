<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'reply_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'reply_id');
    }

    public function getUrlAttribute(): ?string
    {
        $disk = config('support.attachment_disk', 'public');
        if (! $this->file_path) {
            return null;
        }

        try {
            return Storage::disk($disk)->url($this->file_path);
        } catch (\Throwable $e) {
            return asset('storage/' . ltrim($this->file_path, '/'));
        }
    }
}
