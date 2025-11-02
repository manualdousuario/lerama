<?php
declare(strict_types=1);

namespace Lerama\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GdImage;

class ThumbnailService
{
    private string $thumbnailDir;
    private Client $client;

    public function __construct()
    {
        $this->thumbnailDir = __DIR__ . '/../../public/storage/thumbnails/';
        $this->client = new Client();
        
        if (!is_dir($this->thumbnailDir)) {
            @mkdir($this->thumbnailDir, 0755, true);
        }
    }

    public function getThumbnail(string $imageUrl, int $width = 120, int $height = 60): string
    {
        if (empty($imageUrl)) {
            return '';
        }

        $filename = md5($imageUrl . $width . $height) . '.jpg';
        $thumbnailPath = $this->thumbnailDir . $filename;
        $thumbnailUrl = '/storage/thumbnails/' . $filename;

        if (file_exists($thumbnailPath)) {
            return $thumbnailUrl;
        }

        try {
            $tempFile = $this->downloadImage($imageUrl);
            
            if (!$tempFile) {
                return $imageUrl;
            }
            
            $this->createThumbnail($tempFile, $thumbnailPath, $width, $height);
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return $thumbnailUrl;
        } catch (\Exception $e) {
            return $imageUrl;
        }
    }
    
    private function downloadImage(string $url): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            
            $response = $this->client->get($url, [
                'sink' => $tempFile,
                'timeout' => 10,
                'connect_timeout' => 5
            ]);
            
            if ($response->getStatusCode() === 200) {
                return $tempFile;
            }
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return null;
        } catch (GuzzleException $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return null;
        }
    }
    
    private function createThumbnail(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): bool
    {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);
        if (!$sourceImage) {
            return false;
        }
        
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $maxWidth / $maxHeight;
        
        if ($sourceRatio > $targetRatio) {
            $scaledHeight = $maxHeight;
            $scaledWidth = (int)($sourceWidth * ($scaledHeight / $sourceHeight));
            $sourceX = (int)(($sourceWidth - ($sourceHeight * $targetRatio)) / 2);
            $sourceY = 0;
            $sourceUseWidth = (int)($sourceHeight * $targetRatio);
            $sourceUseHeight = $sourceHeight;
        } else {
            $scaledWidth = $maxWidth;
            $scaledHeight = (int)($sourceHeight * ($scaledWidth / $sourceWidth));
            $sourceX = 0;
            $sourceY = (int)(($sourceHeight - ($sourceWidth / $targetRatio)) / 2);
            $sourceUseWidth = $sourceWidth;
            $sourceUseHeight = (int)($sourceWidth / $targetRatio);
        }
        
        $targetImage = imagecreatetruecolor($maxWidth, $maxHeight);
        if (!$targetImage) {
            imagedestroy($sourceImage);
            return false;
        }
        
        if ($mimeType === 'image/png') {
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
        
        $result = $this->saveImage($targetImage, $destPath, 'image/jpeg', 90);
        
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        return $result;
    }
    
    private function createImageFromFile(string $filePath, string $mimeType): GdImage|false
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            case 'image/webp':
                return imagecreatefromwebp($filePath);
            case 'image/bmp':
                return imagecreatefrombmp($filePath);
            default:
                return false;
        }
    }
    
    private function saveImage(GdImage $image, string $filePath, string $mimeType, int $quality = 90): bool
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $filePath, $quality);
            case 'image/png':
                $pngQuality = (int)(9 - (($quality / 100) * 9));
                return imagepng($image, $filePath, $pngQuality);
            case 'image/gif':
                return imagegif($image, $filePath);
            case 'image/webp':
                return imagewebp($image, $filePath, $quality);
            default:
                return imagejpeg($image, $filePath, $quality);
        }
    }
}