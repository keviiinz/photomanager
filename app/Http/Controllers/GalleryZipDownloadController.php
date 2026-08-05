<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class GalleryZipDownloadController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery)
    {
        abort_unless($gallery->isUnlockedFor($request->user()), 403);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer'],
        ]);

        // Re-derive the selection from the database instead of trusting the
        // request: only files that actually belong to this gallery are eligible.
        $media = Media::whereIn('id', $validated['media_ids'])
            ->whereHas('album', fn ($query) => $query->where('gallery_id', $gallery->id))
            ->get();

        abort_if($media->isEmpty(), 404);

        $zipDirectory = storage_path('app/tmp');
        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, recursive: true);
        }

        $zipPath = $zipDirectory.'/'.Str::uuid().'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $usedNames = [];

        foreach ($media as $item) {
            $name = $item->original_name;
            $usedNames[$name] = ($usedNames[$name] ?? -1) + 1;

            if ($usedNames[$name] > 0) {
                $name = pathinfo($name, PATHINFO_FILENAME)."-{$usedNames[$name]}.".pathinfo($name, PATHINFO_EXTENSION);
            }

            $zip->addFromString($name, Storage::disk($item->disk)->get($item->path));
        }

        $zip->close();

        return response()->download($zipPath, Str::slug($gallery->title).'.zip')->deleteFileAfterSend();
    }
}
