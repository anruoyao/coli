<?php

namespace App\Services\Filesystem\Base64Image;

use Exception;
use App\Constants\Filesystem;
use App\Services\Text\SafeUrlFetcher;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;

class Base64ImageService
{
	private $image;
    private $manager;
    private $scaleWidth;
    private $blurRadius;
    private $compressRate = 1;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->scaleWidth = Filesystem::IMAGE_PLACEHOLDER_WIDTH;
        $this->blurRadius = Filesystem::IMAGE_PLACEHOLDER_BLUR;
    }

    public function load(string $imagePath)
    {
        $this->image = $this->manager->read($imagePath);

        return $this;
    }

    public function loadFromUrl(string $imageUrl)
    {
        try {
            $response = app(SafeUrlFetcher::class)->fetch($imageUrl);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new Exception("Image URL returned HTTP {$response->getStatusCode()}.");
            }

            $this->image = $this->manager->read((string) $response->getBody());

            return $this;
        }

        catch (Exception $e) {
            throw new Exception("Failed to get image from URL. {$e->getMessage()}", 0, $e);
        }
    }

    public function setScaleWidth(int $scaleWidth)
    {
        $this->scaleWidth = $scaleWidth;

        return $this;
    }

    public function setBlurRadius(int $blurRadius)
    {
        $this->blurRadius = $blurRadius;

        return $this;
    }

    public function setCompressRate(int $compressRate)
    {
        $this->compressRate = $compressRate;

        return $this;
    }
    
    public function getBase64(): string
    {
        $this->image->scale($this->scaleWidth)->blur($this->blurRadius);
		
		$this->image = $this->image->encode(new WebpEncoder($this->compressRate));

		$base64 = base64_encode((string) $this->image);

        return "data:image/webp;base64,{$base64}";
    }
}
