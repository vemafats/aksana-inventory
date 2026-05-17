<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class PhotoService
{
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    private const MAX_STORED_BYTES = 1024 * 1024;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png'];

    public function uploadPhoto(
        UploadedFile $file,
        string $relatedType,
        string $relatedId,
        User $takenBy,
    ): Photo {
        $this->validateUpload($file);

        $timestamp = now();
        $watermarkText = 'Aksana · '.$timestamp->format('d M Y H:i');
        $filename = sprintf(
            '%s_%s_%s.jpg',
            Str::slug($relatedType, '_'),
            $relatedId,
            $timestamp->format('YmdHis')
        );
        $directory = "photos/{$relatedType}";
        $relativePath = "{$directory}/{$filename}";

        Storage::disk('public')->makeDirectory($directory);

        $fullPath = Storage::disk('public')->path($relativePath);

        $image = $this->imageManager()->read($file->getPathname());
        $this->saveCompressedImage($image, $fullPath);
        $this->applyWatermark($fullPath, $watermarkText);

        return Photo::query()->create([
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'photo_path' => $relativePath,
            'photo_timestamp' => $timestamp,
            'watermark_text' => $watermarkText,
            'taken_by' => $takenBy->id,
        ]);
    }

    public function getPhotoUrl(Photo $photo): string
    {
        return Storage::disk('public')->url($photo->photo_path);
    }

    private function validateUpload(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('Ukuran foto maksimal 5MB.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Format foto harus JPG atau PNG.');
        }
    }

    private function imageManager(): ImageManager
    {
        return new ImageManager(new GdDriver);
    }

    private function saveCompressedImage(ImageInterface $image, string $fullPath): void
    {
        $scalePercent = 100;

        while ($scalePercent >= 50) {
            $working = $scalePercent === 100
                ? $image
                : $image->scale(width: (int) max(1, $image->width() * $scalePercent / 100));

            for ($quality = 90; $quality >= 40; $quality -= 10) {
                $encoded = $working->toJpeg(quality: $quality)->toString();

                if (strlen($encoded) <= self::MAX_STORED_BYTES) {
                    file_put_contents($fullPath, $encoded);

                    return;
                }
            }

            $scalePercent -= 10;
        }

        file_put_contents($fullPath, $image->toJpeg(quality: 40)->toString());
    }

    private function applyWatermark(string $fullPath, string $watermarkText): void
    {
        $image = imagecreatefromjpeg($fullPath);
        if ($image === false) {
            return;
        }

        $font = 3;
        $textWidth = imagefontwidth($font) * strlen($watermarkText);
        $textHeight = imagefontheight($font);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $padding = 8;
        $x = $imageWidth - $textWidth - $padding;
        $y = $imageHeight - $textHeight - $padding;

        $background = imagecolorallocatealpha($image, 0, 0, 0, 40);
        $textColor = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle(
            $image,
            $x - 4,
            $y - 2,
            $x + $textWidth + 4,
            $y + $textHeight + 2,
            $background
        );

        imagestring($image, $font, $x, $y, $watermarkText, $textColor);
        imagejpeg($image, $fullPath, 85);
        imagedestroy($image);
    }
}
