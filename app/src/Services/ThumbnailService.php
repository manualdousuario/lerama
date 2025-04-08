<?php
declare(strict_types=1);

namespace Lerama\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GdImage;

class ThumbnailService
{
    /**
     * Directory to store thumbnails
     * 
     * @var string
     */
    private string $thumbnailDir;
    
    /**
     * Guzzle HTTP client
     * 
     * @var Client
     */
    private Client $client;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->thumbnailDir = __DIR__ . '/../../public/storage/thumbnails/';
        $this->client = new Client();
        
        // Ensure the thumbnail directory exists
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }

    /**
     * Generate a thumbnail from a URL
     * 
     * @param string $imageUrl The URL of the original image
     * @param int $width The desired width of the thumbnail
     * @param int $height The desired height of the thumbnail
     * @return string The URL of the generated thumbnail
     */
    public function getThumbnail(string $imageUrl, int $width = 120, int $height = 60): string
    {
        // If the URL is empty, return an empty string
        if (empty($imageUrl)) {
            return '';
        }

        // Generate a unique filename for the thumbnail
        $filename = md5($imageUrl . $width . $height) . '.jpg';
        $thumbnailPath = $this->thumbnailDir . $filename;
        $thumbnailUrl = '/storage/thumbnails/' . $filename;

        // If the thumbnail already exists, return its URL
        if (file_exists($thumbnailPath)) {
            return $thumbnailUrl;
        }

        try {
            // Download the image using Guzzle
            $tempFile = $this->downloadImage($imageUrl);
            
            if (!$tempFile) {
                return $imageUrl;
            }
            
            // Create thumbnail using native PHP functions
            $this->createThumbnail($tempFile, $thumbnailPath, $width, $height);
            
            // Remove the temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return $thumbnailUrl;
        } catch (\Exception $e) {
            // If there's an error, return the original URL
            return $imageUrl;
        }
    }
    
    /**
     * Download an image from a URL using Guzzle
     * 
     * @param string $url The URL of the image to download
     * @return string|null The path to the downloaded file or null on failure
     */
    private function downloadImage(string $url): ?string
    {
        try {
            // Create a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            
            // Download the image
            $response = $this->client->get($url, [
                'sink' => $tempFile,
                'timeout' => 10,
                'connect_timeout' => 5
            ]);
            
            // Check if the download was successful
            if ($response->getStatusCode() === 200) {
                return $tempFile;
            }
            
            // If not successful, remove the temporary file and return null
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return null;
        } catch (GuzzleException $e) {
            // If there's an error, remove the temporary file if it exists
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return null;
        }
    }
    
    /**
     * Create a thumbnail from an image file using native PHP functions
     * Implements "contain" mode - resize to fit within dimensions while maintaining aspect ratio
     * 
     * @param string $sourcePath Path to the source image
     * @param string $destPath Path where the thumbnail should be saved
     * @param int $maxWidth Maximum width of the thumbnail
     * @param int $maxHeight Maximum height of the thumbnail
     * @return bool True on success, false on failure
     */
    private function createThumbnail(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): bool
    {
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Create source image resource based on file type
        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);
        if (!$sourceImage) {
            return false;
        }
        
        // Calculate dimensions for "cover" mode (fill the entire area, crop if necessary)
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $maxWidth / $maxHeight;
        
        if ($sourceRatio > $targetRatio) {
            // Source image is wider than target - crop the width
            $scaledHeight = $maxHeight;
            $scaledWidth = (int)($sourceWidth * ($scaledHeight / $sourceHeight));
            $sourceX = (int)(($sourceWidth - ($sourceHeight * $targetRatio)) / 2);
            $sourceY = 0;
            $sourceUseWidth = (int)($sourceHeight * $targetRatio);
            $sourceUseHeight = $sourceHeight;
        } else {
            // Source image is taller than target - crop the height
            $scaledWidth = $maxWidth;
            $scaledHeight = (int)($sourceHeight * ($scaledWidth / $sourceWidth));
            $sourceX = 0;
            $sourceY = (int)(($sourceHeight - ($sourceWidth / $targetRatio)) / 2);
            $sourceUseWidth = $sourceWidth;
            $sourceUseHeight = (int)($sourceWidth / $targetRatio);
        }
        
        // Create a new true color image with the exact target dimensions
        $targetImage = imagecreatetruecolor($maxWidth, $maxHeight);
        if (!$targetImage) {
            imagedestroy($sourceImage);
            return false;
        }
        
        // Preserve transparency for PNG images
        if ($mimeType === 'image/png') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $maxWidth, $maxHeight, $transparent);
        }
        
        // Resize and crop the image to fit exactly the target dimensions
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, $sourceX, $sourceY,
            $maxWidth, $maxHeight,
            $sourceUseWidth, $sourceUseHeight
        );
        
        // Save the image
        $result = $this->saveImage($targetImage, $destPath, 'image/jpeg', 90);
        
        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        return $result;
    }
    
    /**
     * Create an image resource from a file
     * 
     * @param string $filePath Path to the image file
     * @param string $mimeType MIME type of the image
     * @return GdImage|false Image resource or false on failure
     */
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
    
    /**
     * Save an image to a file
     * 
     * @param GdImage $image Image resource
     * @param string $filePath Path where the image should be saved
     * @param string $mimeType MIME type of the image to save
     * @param int $quality Quality for JPEG images (0-100)
     * @return bool True on success, false on failure
     */
    private function saveImage(GdImage $image, string $filePath, string $mimeType, int $quality = 90): bool
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $filePath, $quality);
            case 'image/png':
                // Convert quality scale from 0-100 to 0-9
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