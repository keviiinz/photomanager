<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryHeroImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cover_image_uses_the_chosen_cover_when_it_is_featured(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $cover = $album->media()->create($this->fakeMediaAttributes(['is_featured' => true]));

        $gallery->update(['cover_media_id' => $cover->id]);

        $this->assertSame($cover->id, $gallery->publicCoverImage()->id);
    }

    public function test_public_cover_image_falls_back_to_a_featured_photo_when_the_chosen_cover_is_locked(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $lockedCover = $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 0]));
        $featured = $album->media()->create($this->fakeMediaAttributes(['is_featured' => true, 'position' => 1]));

        $gallery->update(['cover_media_id' => $lockedCover->id]);

        $this->assertSame($featured->id, $gallery->publicCoverImage()->id);
    }

    public function test_public_cover_image_is_null_when_no_photo_is_featured(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $album->media()->create($this->fakeMediaAttributes(['is_featured' => false]));

        $this->assertNull($gallery->publicCoverImage());
    }

    public function test_the_public_gallery_page_shows_a_hero_header_with_the_featured_photo(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $featured = $album->media()->create($this->fakeMediaAttributes(['is_featured' => true]));

        $response = $this->get(route('galleries.show', $gallery));

        $response->assertOk();
        $response->assertSee(route('media.show', $featured), false);
    }

    public function test_the_public_gallery_page_has_no_hero_header_without_a_featured_photo(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $album->media()->create($this->fakeMediaAttributes(['is_featured' => false]));

        $response = $this->get(route('galleries.show', $gallery));

        $response->assertOk();
        $response->assertDontSee('Ver galería');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function fakeMediaAttributes(array $overrides = []): array
    {
        return array_merge([
            'type' => 'photo',
            'disk' => 'local',
            'path' => 'galleries/fake-'.uniqid().'.jpg',
            'original_name' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
        ], $overrides);
    }
}
