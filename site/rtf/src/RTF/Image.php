<?php

namespace RTF;

class Image extends Base {

    public $processedImagesPath;
    public $processedImagesUrl;
    public $publicPath;

    function __construct($container, $processedImagesPath = "../public/processed_images/", $processedImagesUrl = "/processed_images/", $publicPath = "../public/") {
        $this->processedImagesPath = $processedImagesPath;
        $this->processedImagesUrl = $processedImagesUrl;
        $this->publicPath = $publicPath;
        $this->container = $container;
    }

    public function __invoke($path, $width = -1, $height = -1, $method = "fill") {
        return $this->resize($path, $width, $height, $method);
    }

    public function resize($path, $width = -1, $height = -1, $method = "fill") {

        if (substr($path, 0, 1) == "/") {
            $path = substr($path, 1);
        }
        $path = $this->publicPath . $path;

        $pathInfo = pathinfo($path);
        $extension = $pathInfo['extension'];
        $filename = $pathInfo['filename'];

        $resizedName = $filename . '_' . $width . 'x' . $height . '_' . $method . '.' . $extension;

        if (file_exists($this->processedImagesPath . $resizedName)) {
            return $this->processedImagesUrl . $resizedName;
        }

        list($srcWidth, $srcHeight, $type) = getimagesize($path);

        // Identify the correct image type and load image accordingly.
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($path);
                $saveImageFunction = 'imagejpeg';
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($path);
                $saveImageFunction = 'imagepng';
                break;
            case IMAGETYPE_GIF:
                $srcImage = imagecreatefromgif($path);
                $saveImageFunction = 'imagegif';
                break;
            default:
                throw new InvalidArgumentException('Unsupported image type: ' . $type);
        }

        // Get original image dimensions.
        list($srcWidth, $srcHeight) = getimagesize($path);

        // Prepare variables for destination image dimensions.
        $dstX = 0;
        $dstY = 0;
        $dstW = $width;
        $dstH = $height;
        $srcX = 0;
        $srcY = 0;
        $srcW = $srcWidth;
        $srcH = $srcHeight;

        // Compute new dimensions and offsets based on method.
        switch ($method) {
            case 'scale':
                // No need to compute anything, the provided dimensions will be used directly.
                break;
            case 'fit_width':
                $dstH = $srcHeight * ($width / $srcWidth);
                break;
            case 'fit_height':
                $dstW = $srcWidth * ($height / $srcHeight);
                break;
            case 'fit':
                $ratio = min($width / $srcWidth, $height / $srcHeight);
                $dstW = $srcWidth * $ratio;
                $dstH = $srcHeight * $ratio;
                break;
            case 'fill':
                $ratio = max($width / $srcWidth, $height / $srcHeight);
                $srcW = $width / $ratio;
                $srcH = $height / $ratio;
                $srcX = ($srcWidth - $srcW) / 2;
                $srcY = ($srcHeight - $srcH) / 2;
                break;
        }

        // Create a new true color image.
        $dstImage = imagecreatetruecolor((int)$dstW, (int)$dstH);

        // Resize the original image into the destination image.
        imagecopyresampled($dstImage, $srcImage, (int)$dstX, (int)$dstY, (int)$srcX, (int)$srcY, (int)$dstW, (int)$dstH, (int)$srcW, (int)$srcH);


        // Save the resized image.
        $saveImageFunction($dstImage, $this->processedImagesPath . $resizedName);

        // Free up memory.
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        // Return the URL of the resized image.
        return $this->processedImagesUrl . $resizedName;

    }

}