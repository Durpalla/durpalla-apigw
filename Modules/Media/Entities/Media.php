<?php

namespace Modules\Media\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Activity\App\Traits\ActivityTrait;
use Modules\Brand\Entities\Brand;

class Media extends Model
{
    use ActivityTrait;
    protected $table = 'medias';
    protected $fillable = [
        'original_name',
        'attachment',
        'type',
        'extension',
        'size',
        'dimension',
        'ratio',
        'user_id',
        'is_cloud'
    ];

    protected $casts = ['is_cloud' => 'bool'];

    protected $hidden = [
        'user_id',
        'created_at',
        'updated_at',
        'is_cloud'
    ];

    protected $logAttributes = ['attachment', 'type', 'extension', 'dimension'];
    protected $logOnlyDirty = true;

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Media {$eventName}";
    }

    public function getAttachmentAttribute($media): string
    {
        $url = asset('default/banner.png');
        if ($media) {
            if ($this->is_cloud) {
                $url = config('media.cdn_enabled', false) ? config('media.cdn_url') . $media : Storage::disk('s3')->url($media);
            } else {
                $url = asset($media);
            }
        }
        return $url;
    }

    public function format(): array
    {
        return [$this->ratio => $this->attachment];
    }
}
