<?php
namespace Modules\Media;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Repository\MediaRepositoryInterface;

class MediaService
{
    private string $dir;
    private MediaRepositoryInterface $repository;

    public function __construct(MediaRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->dir = 'uploads/files/' . date('Y') . '/' . date('m');
    }

    public function upload($file)
    {
        if (is_file($file)) {
            return $this->handle($file);
        }
        return null;
    }

    public function handle($file)
    {
        try {
            if ($file) {
                $imageName = time() . '.' . $file->extension();
                if (!config('media.is_cloud')) {
                    $store = $file->storePubliclyAs('public/' . $this->dir . '/' . $imageName);
                    $url = 'storage/' . $this->dir . '/' . $imageName;
                } else {
                    $url = Storage::disk('s3')->put($this->dir, $file);
                }
                [$width, $height] = getimagesize($file);
                return $this->repository->create([
                    'original_name' => $file->getClientOriginalName(),
                    'attachment' => $url,
                    'extension' => 'jpg',
                    'dimension' => $width . 'X' . $height,
                    'size' => CommonHelper::calculateImageSize($width, $height) . 'kb',
                    'ratio' => CommonHelper::calculateImageRatio($width, $height),
                    'user_id' => auth()->user()->id,
                    'is_cloud' => config('media.is_cloud', false)
                ]);
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'MEDIA_UPLOAD_EXCEPTION'
            ]);

            dd($exception);
        }
        return null;
    }

    public function delete($media): void
    {
        $path = CommonHelper::getStoragePath($media->attachment);
        if(Storage::exists($path)) {
            Storage::delete($path);
        }
        $media->delete();
    }
}
