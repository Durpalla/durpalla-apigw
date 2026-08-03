<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'medias';

    protected $casts = [
        'is_cloud' => 'bool',
    ];

    public function publicUrl(): string
    {
        $path = (string) ($this->attributes['attachment'] ?? '');
        if ($path === '') {
            return asset('default/gateway.png');
        }

        if ($this->is_cloud) {
            $cdn = config('media.cdn_enabled', false) ? (string) config('media.cdn_url', '') : '';
            if ($cdn !== '') {
                return rtrim($cdn, '/') . '/' . ltrim($path, '/');
            }

            return Storage::disk('s3')->url($path);
        }

        return upload_asset($path) ?? asset($path);
    }
}
