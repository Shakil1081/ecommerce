<?php

declare(strict_types=1);

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Log;
use kornrunner\Blurhash\Blurhash;
use Throwable;

class MediaService
{
    /**
     * Compute a BlurHash placeholder string for an image.
     */
    public function computeBlurhash(string $imagePath, int $compX = 4, int $compY = 3): ?string
    {
        if (! file_exists($imagePath) || ! is_readable($imagePath)) {
            return null;
        }

        try {
            $content = file_get_contents($imagePath);
            if ($content === false) {
                return null;
            }

            $sourceImage = @imagecreatefromstring($content);
            if (! $sourceImage instanceof GdImage) {
                return null;
            }

            $origW = imagesx($sourceImage);
            $origH = imagesy($sourceImage);

            if ($origW <= 0 || $origH <= 0) {
                imagedestroy($sourceImage);

                return null;
            }

            // Downscale to 32x32 sample matrix
            $sampleW = 32;
            $sampleH = 32;

            $resized = imagecreatetruecolor($sampleW, $sampleH);
            if (! $resized instanceof GdImage) {
                imagedestroy($sourceImage);

                return null;
            }

            // Fill with white background for PNG transparency support
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);

            imagecopyresampled($resized, $sourceImage, 0, 0, 0, 0, $sampleW, $sampleH, $origW, $origH);

            $pixels = [];
            for ($y = 0; $y < $sampleH; $y++) {
                $row = [];
                for ($x = 0; $x < $sampleW; $x++) {
                    $rgb = imagecolorat($resized, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $row[] = [$r, $g, $b];
                }
                $pixels[] = $row;
            }

            imagedestroy($resized);
            imagedestroy($sourceImage);

            return Blurhash::encode($pixels, $compX, $compY);
        } catch (Throwable $e) {
            Log::warning('Blurhash generation failed: '.$e->getMessage(), ['path' => $imagePath]);

            return null;
        }
    }

    /**
     * Compute a Low-Quality Image Placeholder (LQIP) Base64 Data URI.
     */
    public function computeLqip(string $imagePath, int $width = 16, int $height = 16): ?string
    {
        if (! file_exists($imagePath) || ! is_readable($imagePath)) {
            return null;
        }

        try {
            $content = file_get_contents($imagePath);
            if ($content === false) {
                return null;
            }

            $sourceImage = @imagecreatefromstring($content);
            if (! $sourceImage instanceof GdImage) {
                return null;
            }

            $origW = imagesx($sourceImage);
            $origH = imagesy($sourceImage);

            $resized = imagecreatetruecolor($width, $height);
            if (! $resized instanceof GdImage) {
                imagedestroy($sourceImage);

                return null;
            }

            imagecopyresampled($resized, $sourceImage, 0, 0, 0, 0, $width, $height, $origW, $origH);

            ob_start();
            imagejpeg($resized, null, 40);
            $raw = (string) ob_get_clean();

            imagedestroy($resized);
            imagedestroy($sourceImage);

            return 'data:image/jpeg;base64,'.base64_encode($raw);
        } catch (Throwable $e) {
            Log::warning('LQIP generation failed: '.$e->getMessage(), ['path' => $imagePath]);

            return null;
        }
    }

    /**
     * Compute and attach Blurhash & LQIP to a Media record.
     */
    public function attachPlaceholderData(Media $media): Media
    {
        $path = $media->getPath();

        if (file_exists($path) && str_starts_with($media->mime_type ?? '', 'image/')) {
            $blurhash = $this->computeBlurhash($path);
            if ($blurhash !== null) {
                $media->setCustomProperty('blurhash', $blurhash);
            }

            $lqip = $this->computeLqip($path);
            if ($lqip !== null) {
                $media->setCustomProperty('lqip', $lqip);
            }

            $media->save();
        }

        return $media;
    }

    /**
     * Overlay watermark on an image to protect proprietary import photography.
     */
    public function applyWatermark(
        string $sourcePath,
        string $watermarkPath,
        string $outputPath,
        int $opacity = 60,
        string $position = 'bottom-right'
    ): bool {
        if (! file_exists($sourcePath) || ! file_exists($watermarkPath)) {
            return false;
        }

        try {
            $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
            $watermark = @imagecreatefromstring((string) file_get_contents($watermarkPath));

            if (! $source instanceof GdImage || ! $watermark instanceof GdImage) {
                return false;
            }

            $srcW = imagesx($source);
            $srcH = imagesy($source);
            $wmW = imagesx($watermark);
            $wmH = imagesy($watermark);

            $dstX = match ($position) {
                'center' => (int) (($srcW - $wmW) / 2),
                'top-left' => 20,
                'top-right' => max(0, $srcW - $wmW - 20),
                'bottom-left' => 20,
                default => max(0, $srcW - $wmW - 20),
            };

            $dstY = match ($position) {
                'center' => (int) (($srcH - $wmH) / 2),
                'top-left' => 20,
                'top-right' => 20,
                'bottom-left' => max(0, $srcH - $wmH - 20),
                default => max(0, $srcH - $wmH - 20),
            };

            imagecopymerge($source, $watermark, $dstX, $dstY, 0, 0, $wmW, $wmH, $opacity);
            $result = imagejpeg($source, $outputPath, 90);

            imagedestroy($source);
            imagedestroy($watermark);

            return $result;
        } catch (Throwable $e) {
            Log::warning('Watermark application failed: '.$e->getMessage());

            return false;
        }
    }
}
