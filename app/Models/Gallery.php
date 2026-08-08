<?php

namespace App\Models;

use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $photographer_id
 * @property string $title
 * @property string $client_name
 * @property string $slug
 * @property string $unlock_code
 * @property string|null $location
 * @property Carbon|null $available_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['photographer_id', 'title', 'client_name', 'slug', 'unlock_code', 'cover_media_id', 'location', 'available_until'])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unlock_code' => 'hashed',
            'available_until' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'client_name', 'location', 'available_until'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('gallery')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "Creó la galería \"{$this->title}\"",
                'updated' => "Editó la galería \"{$this->title}\"",
                'deleted' => "Eliminó la galería \"{$this->title}\"",
                default => $event,
            });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::created(function (Gallery $gallery) {
            $gallery->albums()->create(['title' => 'General', 'position' => 0]);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    /**
     * @return HasMany<Album, $this>
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * The photo shown as this gallery's thumbnail: the photographer's explicit
     * choice if there is one, otherwise the first featured photo, otherwise
     * just the first photo in the gallery.
     */
    public function coverImage(): ?Media
    {
        return $this->coverMedia
            ?? $this->media()->where('type', 'photo')->where('is_featured', true)->orderBy('position')->first()
            ?? $this->media()->where('type', 'photo')->orderBy('position')->first();
    }

    /**
     * The photo used as the public gallery page's hero background. Unlike
     * {@see coverImage()}, this never leaks a locked photo to a visitor who
     * hasn't unlocked the gallery — it falls back to the first featured
     * photo whenever the chosen cover isn't one.
     */
    public function publicCoverImage(): ?Media
    {
        $cover = $this->coverImage();

        return $cover?->is_featured
            ? $cover
            : $this->media()->where('type', 'photo')->where('is_featured', true)->orderBy('position')->first();
    }

    /**
     * @return HasManyThrough<Media, Album, $this>
     */
    public function media(): HasManyThrough
    {
        return $this->hasManyThrough(Media::class, Album::class);
    }

    /**
     * Clients who have this gallery in their collection, whether or not
     * they've unlocked the non-featured media yet (see `pivot.unlocked_at`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('unlocked_at')->withTimestamps();
    }

    public function isSavedFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->savedBy()->where('users.id', $user->id)->exists();
    }

    /**
     * A gallery can be unlocked either by an account (tracked in the `gallery_user`
     * pivot) or, for visitors without an account, by a signed cookie set in
     * {@see unlockForGuest()} — having an account is a convenience, not a requirement.
     */
    public function isUnlockedFor(?User $user): bool
    {
        if ($user) {
            if ($user->id === $this->photographer_id) {
                return true;
            }

            if ($this->savedBy()->where('users.id', $user->id)->whereNotNull('unlocked_at')->exists()) {
                return true;
            }
        }

        return request()->hasCookie($this->guestUnlockCookieName());
    }

    public function unlockForGuest(): void
    {
        Cookie::queue(Cookie::forever($this->guestUnlockCookieName(), '1'));
    }

    protected function guestUnlockCookieName(): string
    {
        return "gallery_unlocked_{$this->id}";
    }

    /**
     * Add the gallery to a client's collection without granting access to
     * the non-featured media. Does nothing if it's already saved/unlocked.
     */
    public function saveFor(User $user): void
    {
        if ($this->isSavedFor($user)) {
            return;
        }

        $this->savedBy()->attach($user->id, ['unlocked_at' => null]);
    }

    public function unlockFor(User $user): void
    {
        $this->savedBy()->syncWithoutDetaching([
            $user->id => ['unlocked_at' => now()],
        ]);
    }

    /**
     * The gallery's title, stripped of characters that aren't safe in a filename,
     * used as the base name for downloaded files instead of the original camera
     * filename.
     */
    public function downloadFilenameBase(): string
    {
        $safe = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $this->title));

        return $safe !== '' ? $safe : 'galeria';
    }
}
