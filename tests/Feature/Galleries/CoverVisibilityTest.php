<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoverVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Gallery, 1: Media, 2: Media}
     */
    protected function galleryWithCover(bool $showCoverInGallery): array
    {
        $gallery = Gallery::factory()->create(['show_cover_in_gallery' => $showCoverInGallery]);
        $albumId = $gallery->albums()->first()->id;

        $cover = Media::factory()->featured()->create(['album_id' => $albumId, 'position' => 0]);
        $other = Media::factory()->featured()->create(['album_id' => $albumId, 'position' => 1]);
        $gallery->update(['cover_media_id' => $cover->id]);

        return [$gallery, $cover, $other];
    }

    public function test_cover_is_shown_in_the_gallery_by_default(): void
    {
        $this->assertTrue(Gallery::factory()->create()->show_cover_in_gallery);

        [$gallery, $cover, $other] = $this->galleryWithCover(true);

        $ids = Livewire::test('pages::galleries.show', ['gallery' => $gallery])->get('activeAlbumMedia')->pluck('id');

        $this->assertEqualsCanonicalizing([$cover->id, $other->id], $ids->all());
    }

    public function test_hidden_cover_only_appears_as_the_cover(): void
    {
        [$gallery, $cover, $other] = $this->galleryWithCover(false);

        $component = Livewire::test('pages::galleries.show', ['gallery' => $gallery]);

        $this->assertSame($cover->id, $component->get('heroImage')->id);
        $this->assertSame([$other->id], $component->get('activeAlbumMedia')->pluck('id')->all());
    }

    public function test_photographer_can_toggle_the_option(): void
    {
        $gallery = Gallery::factory()->create();

        $this->actingAs($gallery->photographer);

        $component = Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('toggleCoverInGallery');
        $this->assertFalse($gallery->refresh()->show_cover_in_gallery);

        $component->call('toggleCoverInGallery');
        $this->assertTrue($gallery->refresh()->show_cover_in_gallery);
    }
}
