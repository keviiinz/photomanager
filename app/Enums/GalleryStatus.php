<?php

namespace App\Enums;

/**
 * Whether a gallery can be seen by anyone other than its photographer.
 */
enum GalleryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Borrador'),
            self::Published => __('Publicada'),
        };
    }
}
