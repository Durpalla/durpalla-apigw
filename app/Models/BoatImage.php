<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BoatImage extends Model
{
    protected $fillable = [
        'boat_id',
        'image_path',
        'type',
        'sort_order',
    ];

    protected $appends = [
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'boat_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class, 'boat_id', 'id');
    }

    public function getImageUrlAttribute(): string
    {
        $path = trim((string) ($this->attributes['image_path'] ?? ''));
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            return $path;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $base = rtrim((string) config('boat_rental.image_public_base_url', ''), '/');
        if ($base !== '') {
            return $base.'/storage/'.$relative;
        }

        $disk = config('filesystems.uploads_disk', 'public');
        try {
            return Storage::disk($disk)->url($relative);
        } catch (\Throwable) {
            return asset('storage/'.$relative);
        }
    }
}
