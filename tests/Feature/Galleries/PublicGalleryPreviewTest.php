<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicGalleryPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_only_sees_featured_media_in_the_public_gallery(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $featured = $album->media()->create($this->fakeMediaAttributes(['is_featured' => true]));
        $hidden = $album->media()->create($this->fakeMediaAttributes(['is_featured' => false]));

        $component = Livewire::test('pages::galleries.show', ['gallery' => $gallery]);

        $visibleIds = $component->get('activeAlbumMedia')->pluck('id')->all();

        $this->assertContains($featured->id, $visibleIds);
        $this->assertNotContains($hidden->id, $visibleIds);
    }

    public function test_non_featured_media_cannot_be_viewed_directly_by_a_guest(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();
        Storage::disk('local')->put('galleries/hidden.jpg', 'fake-bytes');

        $hidden = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => false,
            'path' => 'galleries/hidden.jpg',
        ]));

        $this->get(route('media.show', $hidden))->assertForbidden();
    }

    public function test_featured_media_is_served_watermarked_and_cannot_be_downloaded_by_a_guest(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $image = imagecreatetruecolor(200, 150);
        ob_start();
        imagejpeg($image);
        Storage::disk('local')->put('galleries/featured.jpg', ob_get_clean());

        $featured = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => true,
            'path' => 'galleries/featured.jpg',
        ]));

        $this->get(route('media.show', $featured))->assertOk();
        $this->assertTrue(Storage::disk('local')->exists('galleries/featured.watermarked.jpg'));

        $this->get(route('media.download', $featured))->assertForbidden();
    }

    public function test_featured_photo_response_is_never_cached_since_it_changes_after_unlock(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $image = imagecreatetruecolor(200, 150);
        ob_start();
        imagejpeg($image);
        Storage::disk('local')->put('galleries/featured.jpg', ob_get_clean());

        $featured = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => true,
            'path' => 'galleries/featured.jpg',
        ]));

        $response = $this->get(route('media.show', $featured));

        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
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
