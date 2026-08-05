<?php

namespace App\Http\Controllers;

use App\Actions\Media\GenerateWatermarkedPreview;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Stream a media file for inline viewing (image tag / video player).
     * Featured photos are public but served watermarked until the gallery is unlocked.
     */
    public function show(Request $request, Media $media)
    {
        abort_unless($media->isViewableBy($request->user()), 403);

        $disk = Storage::disk($media->disk);

        if ($media->is_featured && $media->isPhoto()) {
            // The response for a featured photo's URL changes (watermarked vs. original)
            // once the gallery is unlocked, so it must never be cached across that transition.
            $path = $this->needsWatermark($media, $request)
                ? app(GenerateWatermarkedPreview::class)($media)
                : $media->path;

            $response = $disk->response($path);
            $response->setPrivate()->setMaxAge(0);
            $response->headers->addCacheControlDirective('no-store');

            return $response;
        }

        return $disk->response($media->path);
    }

    /**
     * Download the original file. Only available once the gallery is unlocked
     * for the requesting user — featured/watermarked previews cannot be downloaded.
     */
    public function download(Request $request, Media $media)
    {
        abort_unless($media->album->gallery->isUnlockedFor($request->user()), 403);

        return Storage::disk($media->disk)->download($media->path, $media->original_name);
    }

    protected function needsWatermark(Media $media, Request $request): bool
    {
        return $media->is_featured
            && $media->isPhoto()
            && ! $media->album->gallery->isUnlockedFor($request->user());
    }
}
