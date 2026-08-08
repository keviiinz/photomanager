<?php

namespace App\Console\Commands;

use App\Models\HomeImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:import-legacy-home-images')]
#[Description('One-off import of the static public/fotos_home images into the home_images table, uploading them to the configured disk.')]
class ImportLegacyHomeImages extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (HomeImage::query()->exists()) {
            $this->info('Ya hay imágenes registradas, no se importó nada.');

            return;
        }

        $files = glob(public_path('fotos_home').'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        natsort($files);

        if ($files === []) {
            $this->info('No se encontraron imágenes en public/fotos_home.');

            return;
        }

        $disk = config('filesystems.default');
        $position = 0;

        foreach (array_values($files) as $file) {
            $filename = basename($file);
            $path = Storage::disk($disk)->putFileAs('home', $file, $filename);

            HomeImage::create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $filename,
                'mime_type' => mime_content_type($file) ?: 'image/jpeg',
                'size_bytes' => filesize($file) ?: 0,
                'position' => $position++,
                'is_primary' => $position === 1,
            ]);

            $this->info("Importada: {$filename}");
        }

        $this->info('Listo.');
    }
}
