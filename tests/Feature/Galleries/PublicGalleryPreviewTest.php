<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicGalleryPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_featured_media_plus_up_to_two_teasers_in_the_public_gallery(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $featured = $album->media()->create($this->fakeMediaAttributes(['is_featured' => true, 'position' => 0]));
        $teaserOne = $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 1]));
        $teaserTwo = $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 2]));
        $stillHidden = $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 3]));

        $component = Livewire::test('pages::galleries.show', ['gallery' => $gallery]);

        $visibleIds = $component->get('activeAlbumMedia')->pluck('id')->all();

        $this->assertContains($featured->id, $visibleIds);
        $this->assertContains($teaserOne->id, $visibleIds);
        $this->assertContains($teaserTwo->id, $visibleIds);
        $this->assertNotContains($stillHidden->id, $visibleIds);
    }

    public function test_non_featured_media_beyond_the_teaser_limit_cannot_be_viewed_directly_by_a_guest(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 1]));
        $album->media()->create($this->fakeMediaAttributes(['is_featured' => false, 'position' => 2]));

        Storage::disk('local')->put('galleries/hidden.jpg', 'fake-bytes');
        $hidden = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => false,
            'position' => 3,
            'path' => 'galleries/hidden.jpg',
        ]));

        $this->get(route('media.show', $hidden))->assertForbidden();
    }

    public function test_teaser_photo_is_served_blurred_and_cannot_be_downloaded_by_a_guest(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        $image = imagecreatetruecolor(200, 150);
        ob_start();
        imagejpeg($image);
        Storage::disk('local')->put('galleries/teaser.jpg', ob_get_clean());

        $teaser = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => false,
            'position' => 1,
            'path' => 'galleries/teaser.jpg',
        ]));

        $this->get(route('media.show', $teaser))->assertOk();
        $this->assertTrue(Storage::disk('local')->exists('galleries/teaser.blurred.jpg'));

        $this->get(route('media.download', $teaser))->assertForbidden();
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

    public function test_downloaded_media_is_named_after_the_gallery_instead_of_the_original_filename(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create(['title' => 'Boda de Ana & Marco']);
        $album = $gallery->albums()->first();
        Storage::disk('local')->put('galleries/original-camera-name.jpg', 'fake-bytes');

        $media = $album->media()->create($this->fakeMediaAttributes([
            'is_featured' => true,
            'path' => 'galleries/original-camera-name.jpg',
            'original_name' => 'original-camera-name.jpg',
        ]));

        $response = $this->withCookie("gallery_unlocked_{$gallery->id}", '1')
            ->get(route('media.download', $media));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('Boda de Ana & Marco.jpg', $response->headers->get('Content-Disposition'));
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
