<?php

namespace App\Services;

use App\Models\Item;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    private const PNG_SIZE = 200;

    public function generateQrCode(Item $item): string
    {
        return base64_encode($this->generatePngBinary($item->barcode));
    }

    public function generateQrCodeSvg(Item $item): string
    {
        return QrCode::format('svg')
            ->size(self::PNG_SIZE)
            ->errorCorrection('M')
            ->color(0, 0, 0)
            ->backgroundColor(255, 255, 255)
            ->generate($item->barcode);
    }

    public function saveQrCode(Item $item): string
    {
        $path = $this->storagePath($item);
        $png = $this->generatePngBinary($item->barcode);

        Storage::disk('public')->put($path, $png);

        return $path;
    }

    public function storagePath(Item $item): string
    {
        $safeSku = Str::replace(['/', '\\'], '-', $item->sku);

        return "qrcodes/{$safeSku}.png";
    }

    public function ensureQrCodeFile(Item $item): string
    {
        $path = $this->storagePath($item);

        if (! Storage::disk('public')->exists($path)) {
            $this->saveQrCode($item);
        }

        return $path;
    }

    private function generatePngBinary(string $content): string
    {
        if (extension_loaded('imagick')) {
            return QrCode::format('png')
                ->size(self::PNG_SIZE)
                ->errorCorrection('M')
                ->color(0, 0, 0)
                ->backgroundColor(255, 255, 255)
                ->generate($content);
        }

        return $this->renderPngWithGd($content);
    }

    private function renderPngWithGd(string $content): string
    {
        $qrCode = Encoder::encode($content, ErrorCorrectionLevel::M());
        $matrix = $qrCode->getMatrix();
        $moduleCount = $matrix->getWidth();
        $moduleSize = max(1, (int) floor(self::PNG_SIZE / $moduleCount));
        $imageSize = $moduleSize * $moduleCount;

        $image = imagecreatetruecolor($imageSize, $imageSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                imagefilledrectangle(
                    $image,
                    $x * $moduleSize,
                    $y * $moduleSize,
                    ($x + 1) * $moduleSize - 1,
                    ($y + 1) * $moduleSize - 1,
                    $black
                );
            }
        }

        ob_start();
        imagepng($image);
        $pngData = ob_get_clean() ?: '';
        imagedestroy($image);

        return $pngData;
    }
}
