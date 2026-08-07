<?php

namespace App\Actions\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Produces (and caches on disk) a heavily obscured "teaser" copy of a locked photo —
 * shrunk down to a handful of pixels and scaled back up, so it reads as an
 * unrecognizable smear of color no matter how it's displayed. Unlike a CSS blur,
 * this never sends the original bytes to the browser before the gallery is unlocked.
 */
class GenerateBlurredPreview
{
    /**
     * @return string the disk path of the blurred copy
     */
    public function __invoke(Media $media): string
    {
        $disk = Storage::disk($media->disk);
        $blurredPath = $this->blurredPath($media);

        if (! $disk->exists($blurredPath)) {
            $disk->put($blurredPath, $this->render($media));
        }

        return $blurredPath;
    }

    protected function blurredPath(Media $media): string
    {
        $info = pathinfo($media->path);
        $directory = $info['dirname'] === '.' ? '' : $info['dirname'].'/';

        return $directory.$info['filename'].'.blurred.jpg';
    }

    protected function render(Media $media): string
    {
        $source = imagecreatefromstring(Storage::disk($media->disk)->get($media->path));

        $width = imagesx($source);
        $height = imagesy($source);

        $tinyWidth = 16;
        $tinyHeight = max(1, (int) round($height * ($tinyWidth / $width)));

        $tiny = imagecreatetruecolor($tinyWidth, $tinyHeight);
        imagecopyresampled($tiny, $source, 0, 0, 0, 0, $tinyWidth, $tinyHeight, $width, $height);

        $result = imagecreatetruecolor($width, $height);
        imagecopyresized($result, $tiny, 0, 0, 0, 0, $width, $height, $tinyWidth, $tinyHeight);
        imagefilter($result, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($result, IMG_FILTER_GAUSSIAN_BLUR);

        ob_start();
        imagejpeg($result, quality: 70);

        imagedestroy($source);
        imagedestroy($tiny);
        imagedestroy($result);

        return ob_get_clean();
    }
}
