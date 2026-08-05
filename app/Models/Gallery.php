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
#[Fillable(['photographer_id', 'title', 'client_name', 'slug', 'unlock_code', 'location', 'available_until'])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

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

    public function isUnlockedFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->id === $this->photographer_id) {
            return true;
        }

        return $this->savedBy()->where('users.id', $user->id)->whereNotNull('unlocked_at')->exists();
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
}
