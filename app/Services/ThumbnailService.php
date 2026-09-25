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
 *
 * Failures leave a {md5}.fail marker so the same broken, oversized or
 * unsupported image isn't downloaded again on every page view and cache warm.
 * It lives on disk rather than in the cache, which is flushed on every write.
 */
class ThumbnailService
{
    public const FAILURE_TTL_SECONDS = 86400;

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

        if ($this->recentlyFailed($imageUrl, $width, $height)) {
            return $imageUrl;
        }

        $tempFile = null;

        try {
            $tempFile = $this->downloadImage($imageUrl);

            if (! $tempFile) {
                return $this->markFailed($imageUrl, $width, $height);
            }

            $targetPath = $disk->path($relative);
            $dir = dirname($targetPath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if (! $this->createThumbnail($tempFile, $targetPath, $width, $height)) {
                return $this->markFailed($imageUrl, $width, $height);
            }

            $disk->delete($this->failureMarker($imageUrl, $width, $height));

            return $this->thumbnailUrl($imageUrl, $width, $height);
        } catch (\Throwable) {
            return $this->markFailed($imageUrl, $width, $height);
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

    public function recentlyFailed(string $imageUrl, int $width, int $height): bool
    {
        $path = Storage::disk('public')->path($this->failureMarker($imageUrl, $width, $height));
        $mtime = @filemtime($path);

        return $mtime !== false && time() - $mtime < self::FAILURE_TTL_SECONDS;
    }

    private function markFailed(string $imageUrl, int $width, int $height): string
    {
        try {
            Storage::disk('public')->put($this->failureMarker($imageUrl, $width, $height), '');
        } catch (\Throwable) {
            // Best effort: without the marker we just retry sooner.
        }

        return $imageUrl;
    }

    private function failureMarker(string $imageUrl, int $width, int $height): string
    {
        return 'thumbnails/'.md5($imageUrl.$width.$height).'.fail';
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

        if ($this->recentlyFailed($imageUrl, $width, $height)) {
            return $imageUrl;
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

        if (! self::fitsInMemory($sourceWidth, $sourceHeight)) {
            return false;
        }

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

    public static function fitsInMemory(int $width, int $height, ?int $limitBytes = null, ?int $usedBytes = null): bool
    {
        $limit = $limitBytes ?? self::memoryLimitBytes();
        if ($limit <= 0) {
            return true;
        }

        $needed = (int) ($width * $height * 4 * 1.5);

        return $needed < $limit - ($usedBytes ?? memory_get_usage(true));
    }

    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
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
