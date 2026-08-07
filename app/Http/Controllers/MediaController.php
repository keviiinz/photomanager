<?php

namespace App\Http\Controllers;

use App\Actions\Media\GenerateBlurredPreview;
use App\Actions\Media\GenerateWatermarkedPreview;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Stream a media file for inline viewing (image tag / video player).
     * Featured photos are public but served watermarked, and teaser photos
     * blurred, until the gallery is unlocked.
     */
    public function show(Request $request, Media $media)
    {
        abort_unless($media->isViewableBy($request->user()), 403);

        $disk = Storage::disk($media->disk);

        if ($media->isPhoto() && ! $media->album->gallery->isUnlockedFor($request->user()) && ($media->is_featured || $media->isTeaser())) {
            // The response for a locked photo's URL changes (watermarked/blurred vs.
            // original) once the gallery is unlocked, so it must never be cached
            // across that transition.
            $path = $media->is_featured
                ? app(GenerateWatermarkedPreview::class)($media)
                : app(GenerateBlurredPreview::class)($media);

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
        $gallery = $media->album->gallery;

        abort_unless($gallery->isUnlockedFor($request->user()), 403);

        activity('gallery')
            ->causedBy($request->user())
            ->performedOn($gallery)
            ->withProperties(['media_id' => $media->id, 'original_name' => $media->original_name])
            ->event('media_downloaded')
            ->log("Descargó el archivo \"{$media->original_name}\" de la galería \"{$gallery->title}\"");

        $extension = pathinfo($media->original_name, PATHINFO_EXTENSION);
        $filename = $gallery->downloadFilenameBase().($extension !== '' ? ".{$extension}" : '');

        return Storage::disk($media->disk)->download($media->path, $filename);
    }
}
