<?php

namespace App\Actions\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Produces (and caches on disk) a watermarked copy of a featured photo, so the
 * public preview never exposes the clean original before a gallery is unlocked.
 */
class GenerateWatermarkedPreview
{
    /**
     * @return string the disk path of the watermarked copy
     */
    public function __invoke(Media $media): string
    {
        $disk = Storage::disk($media->disk);
        $watermarkedPath = $this->watermarkedPath($media);

        if (! $disk->exists($watermarkedPath)) {
            $disk->put($watermarkedPath, $this->render($media));
        }

        return $watermarkedPath;
    }

    protected function watermarkedPath(Media $media): string
    {
        $info = pathinfo($media->path);
        $directory = $info['dirname'] === '.' ? '' : $info['dirname'].'/';

        return $directory.$info['filename'].'.watermarked.jpg';
    }

    protected function render(Media $media): string
    {
        $image = imagecreatefromstring(Storage::disk($media->disk)->get($media->path));

        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $text = $media->album->gallery->photographer->name;

        $tile = $this->buildWatermarkTile($text, $width);
        $tileWidth = imagesx($tile);
        $tileHeight = imagesy($tile);
        $stepX = $tileWidth + (int) ($tileWidth * 0.2);
        $stepY = $tileHeight + (int) ($tileHeight * 1.6);

        for ($y = -$tileHeight; $y < $height + $stepY; $y += $stepY) {
            for ($x = -$tileWidth; $x < $width + $stepX; $x += $stepX) {
                imagecopy($image, $tile, $x, $y, 0, 0, $tileWidth, $tileHeight);
            }
        }

        ob_start();
        imagejpeg($image, quality: 85);

        return ob_get_clean();
    }

    /**
     * Renders the watermark text at GD's largest built-in bitmap font and
     * upscales it with smoothing, since imagestring() can't draw arbitrarily
     * large text directly and we don't want to depend on bundling a TTF font.
     * The scale is tied to the photo's own width so the watermark reads at a
     * consistent, sizeable proportion no matter the photo's resolution.
     */
    protected function buildWatermarkTile(string $text, int $imageWidth): \GdImage
    {
        $font = 5;
        $baseWidth = imagefontwidth($font) * strlen($text);
        $baseHeight = imagefontheight($font);

        $targetWidth = max($baseWidth, (int) ($imageWidth * 0.3));
        $scale = $targetWidth / $baseWidth;

        $small = imagecreatetruecolor($baseWidth, $baseHeight);
        imagesavealpha($small, true);
        imagealphablending($small, false);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, $font, 0, 0, $text, imagecolorallocatealpha($small, 255, 255, 255, 55));

        $scaledWidth = (int) round($baseWidth * $scale);
        $scaledHeight = (int) round($baseHeight * $scale);
        $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagesavealpha($scaled, true);
        imagealphablending($scaled, false);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $small, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $baseWidth, $baseHeight);

        imagedestroy($small);

        return $scaled;
    }
}
