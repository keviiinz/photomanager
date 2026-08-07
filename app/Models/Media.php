<?php

namespace App\Models;

use App\Actions\Media\GenerateBlurredPreview;
use App\Enums\MediaType;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
    /** @use HasFactory<MediaFactory> */
    use HasFactory, LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['album_id', 'original_name', 'type', 'is_featured'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('media')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "Subió el archivo \"{$this->original_name}\"",
                'updated' => "Actualizó el archivo \"{$this->original_name}\"",
                'deleted' => "Eliminó el archivo \"{$this->original_name}\"",
                default => $event,
            });
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
        return $this->is_featured || $this->isTeaser() || $this->album->gallery->isUnlockedFor($user);
    }

    /**
     * Teasers are the first couple of locked photos in an album, shown obscured
     * (see {@see GenerateBlurredPreview}) as a nudge to unlock
     * the rest — a preview of what's there, never the real thing.
     */
    public function isTeaser(): bool
    {
        if (! $this->isPhoto() || $this->is_featured) {
            return false;
        }

        return $this->album->media()
            ->where('is_featured', false)
            ->where('type', MediaType::Photo)
            ->limit(2)
            ->pluck('id')
            ->contains($this->id);
    }
}
