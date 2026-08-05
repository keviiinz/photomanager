<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $album_id
 * @property MediaType $type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property bool $is_featured
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['album_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'is_featured', 'position'])]
class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function isPhoto(): bool
    {
        return $this->type === MediaType::Photo;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }

    /**
     * Whether the given (possibly guest) user is allowed to view/stream this file.
     */
    public function isViewableBy(?User $user): bool
    {
        return $this->is_featured || $this->album->gallery->isUnlockedFor($user);
    }
}
