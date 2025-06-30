<?php


namespace App\Services;


class UploadService
{
    public function upload($file, $dir = 'files')
    {
        if($file) {
            $imageName = time() . '.' . $file->extension();
            $file->move(public_path($dir), $imageName);
            return $dir . '/' . $imageName;
        }
        return false;
    }

    public function uploadResize($file, array $dim)
    {

    }
}
