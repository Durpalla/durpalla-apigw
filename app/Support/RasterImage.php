<?php

namespace App\Support;

/**
 * Fit-resize and save raster images using ext-gd (no Intervention dependency).
 */
final class RasterImage
{
    /**
     * Resize so the image fits inside [$maxWidth, $maxHeight] while keeping aspect ratio, then save.
     * If GD is missing or the format is unsupported, copies the source file to the destination.
     */
    public static function resizeToFit(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth,
        int $maxHeight,
    ): bool {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return false;
        }

        if (! extension_loaded('gd')) {
            return (bool) @copy($sourcePath, $destinationPath);
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return (bool) @copy($sourcePath, $destinationPath);
        }

        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1) {
            return (bool) @copy($sourcePath, $destinationPath);
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $src = self::imageCreateFromFile($sourcePath, $type);
        if ($src === false) {
            return (bool) @copy($sourcePath, $destinationPath);
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);

            return (bool) @copy($sourcePath, $destinationPath);
        }

        $alphaSource = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
        if ($alphaSource) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
            imagealphablending($dst, true);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $ext = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION) ?: 'jpg');
        $ok = self::saveImage($dst, $destinationPath, $ext);

        imagedestroy($src);
        imagedestroy($dst);

        return $ok || (bool) @copy($sourcePath, $destinationPath);
    }

    /**
     * @return \GdImage|false
     */
    private static function imageCreateFromFile(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($path)
                : false,
            default => false,
        };
    }

    private static function saveImage(\GdImage $dst, string $path, string $ext): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($dst, $path, 88),
            'png' => imagepng($dst, $path, 6),
            'gif' => imagegif($dst, $path),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $path, 88) : false,
            default => imagejpeg($dst, $path, 88),
        };
    }
}
