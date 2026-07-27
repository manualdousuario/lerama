<?php

namespace App\Services;

use App\Support\HttpClient;
use GdImage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

/**
 * Generates local thumbnails from remote images (center crop + GD resize),
 * written to storage/app/public/thumbnails and served through storage:link at
 * /storage/thumbnails/{md5}.jpg. Falls back to the original URL on any failure.
 */
class ThumbnailService
{
    public function getThumbnail(string $imageUrl, int $width = 120, int $height = 60): string
    {
        if (empty($imageUrl)) {
            return '';
        }

        $disk = Storage::disk('public');
        $relative = 'thumbnails/'.md5($imageUrl.$width.$height).'.jpg';

        if ($disk->exists($relative)) {
            return $this->thumbnailUrl($imageUrl, $width, $height);
        }

        $tempFile = null;

        try {
            $tempFile = $this->downloadImage($imageUrl);

            if (! $tempFile) {
                return $imageUrl;
            }

            $targetPath = $disk->path($relative);
            $dir = dirname($targetPath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if (! $this->createThumbnail($tempFile, $targetPath, $width, $height)) {
                return $imageUrl;
            }

            return $this->thumbnailUrl($imageUrl, $width, $height);
        } catch (\Throwable) {
            return $imageUrl;
        } finally {
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function hasThumbnail(string $imageUrl, int $width, int $height): bool
    {
        return Storage::disk('public')->exists('thumbnails/'.md5($imageUrl.$width.$height).'.jpg');
    }

    public function thumbnailUrl(string $imageUrl, int $width, int $height): string
    {
        return '/storage/thumbnails/'.md5($imageUrl.$width.$height).'.jpg';
    }

    public function getThumbnailDeferred(string $imageUrl, int $width = 120, int $height = 60): string
    {
        if ($imageUrl === '') {
            return '';
        }

        if ($this->hasThumbnail($imageUrl, $width, $height)) {
            return $this->thumbnailUrl($imageUrl, $width, $height);
        }

        defer(fn () => $this->getThumbnail($imageUrl, $width, $height));

        return $imageUrl;
    }

    private function downloadImage(string $url): ?string
    {
        $proxy = new ProxyService;
        $attempts = $proxy->buildAttemptConfigs([
            'timeout' => 10,
            'connect_timeout' => 5,
        ] + HttpClient::imageConfig());

        foreach ($attempts as $attempt) {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');

            try {
                $client = new Client($attempt['config']);
                $response = $client->get($url, ['sink' => $tempFile]);

                if ($response->getStatusCode() === 200) {
                    return $tempFile;
                }
            } catch (\Throwable) {
                // Try the next config.
            }

            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        return null;
    }

    private function createThumbnail(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): bool
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$sourceWidth, $sourceHeight] = $imageInfo;
        $mimeType = $imageInfo['mime'];

        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);
        if (! $sourceImage) {
            return false;
        }

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $maxWidth / $maxHeight;

        if ($sourceRatio > $targetRatio) {
            $sourceX = (int) (($sourceWidth - ($sourceHeight * $targetRatio)) / 2);
            $sourceY = 0;
            $sourceUseWidth = (int) ($sourceHeight * $targetRatio);
            $sourceUseHeight = $sourceHeight;
        } else {
            $sourceX = 0;
            $sourceY = (int) (($sourceHeight - ($sourceWidth / $targetRatio)) / 2);
            $sourceUseWidth = $sourceWidth;
            $sourceUseHeight = (int) ($sourceWidth / $targetRatio);
        }

        $targetImage = imagecreatetruecolor($maxWidth, $maxHeight);
        if (! $targetImage) {
            return false;
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/avif') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $maxWidth, $maxHeight, $transparent);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, $sourceX, $sourceY,
            $maxWidth, $maxHeight,
            $sourceUseWidth, $sourceUseHeight
        );

        return imagejpeg($targetImage, $destPath, 90);
    }

    private function createImageFromFile(string $filePath, string $mimeType): GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/gif' => imagecreatefromgif($filePath),
            'image/webp' => imagecreatefromwebp($filePath),
            'image/bmp' => imagecreatefrombmp($filePath),
            'image/avif' => imagecreatefromavif($filePath),
            default => false,
        };
    }
}
