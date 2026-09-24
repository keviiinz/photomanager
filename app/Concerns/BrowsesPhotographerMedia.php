<?php

namespace App\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Shared behavior for the photographer's cross-gallery media views (Biblioteca, Destacados):
 * newest-first listing with "load more" pagination and toggling the featured flag.
 */
trait BrowsesPhotographerMedia
{
    public int $perPage = 48;

    /**
     * The base query for this view; override to narrow it down (e.g. featured only).
     *
     * @return Builder<Media>
     */
    protected function mediaQuery(): Builder
    {
        return Media::query()->ownedBy(Auth::user());
    }

    /**
     * @return Collection<int, Media>
     */
    #[Computed]
    public function media(): Collection
    {
        return $this->mediaQuery()
            ->with('album.gallery')
            ->latest()
            ->latest('id')
            ->limit($this->perPage)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return $this->mediaQuery()->count();
    }

    public function loadMore(): void
    {
        $this->perPage += 48;
    }

    public function toggleFeatured(int $mediaId): void
    {
        $media = Media::query()->ownedBy(Auth::user())->findOrFail($mediaId);

        $media->update(['is_featured' => ! $media->is_featured]);

        unset($this->media, $this->total);
    }
}
