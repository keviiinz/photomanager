<?php

namespace App\Models;

use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $gallery_id
 * @property string $title
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['gallery_id', 'title', 'position'])]
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['gallery_id', 'title'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('album')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "Creó el álbum \"{$this->title}\"",
                'updated' => "Editó el álbum \"{$this->title}\"",
                'deleted' => "Eliminó el álbum \"{$this->title}\"",
                default => $event,
            });
    }

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('position');
    }
}
