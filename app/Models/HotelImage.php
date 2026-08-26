<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HotelImage extends Model
{
    protected $fillable = [
        'hotel_id',
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

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'id');
    }

    /**
     * Public URL for merchant / admin UIs.
     * Uses FILESYSTEM_PUBLIC_URL / UPLOADS_PUBLIC_BASE_URL (CDN) when configured.
     */
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
        // Uploads saved with a local APP_URL are unreachable from the merchant web app.
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
