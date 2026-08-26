<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HotelRoomImage extends Model
{
    protected $fillable = [
        'hotel_room_id',
        'image_path',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function room()
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id', 'id');
    }

    public function getImageUrlAttribute(): string
    {
        $path = trim((string) ($this->attributes['image_path'] ?? ''));
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            return $this->normalizeAbsoluteUrl($path);
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'default/')) {
            return asset($relative);
        }

        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $disk = config('filesystems.uploads_disk', 'public');
        try {
            return Storage::disk($disk)->url($relative);
        } catch (\Throwable) {
            return upload_asset('storage/'.$relative) ?? asset('storage/'.$relative);
        }
    }

    private function normalizeAbsoluteUrl(string $path): string
    {
        if (str_starts_with($path, '//')) {
            $path = 'https:'.$path;
        }

        $parts = parse_url($path);
        if (! is_array($parts) || empty($parts['path'])) {
            return $path;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_contains($host, 'durpalla.site')
            || str_ends_with($host, '.local')) {
            $filePath = ltrim((string) $parts['path'], '/');
            if (str_starts_with($filePath, 'storage/')) {
                $filePath = substr($filePath, strlen('storage/'));
            }

            $disk = config('filesystems.uploads_disk', 'public');
            try {
                return Storage::disk($disk)->url($filePath);
            } catch (\Throwable) {
                return upload_asset('storage/'.$filePath) ?? asset('storage/'.$filePath);
            }
        }

        return $path;
    }
}
