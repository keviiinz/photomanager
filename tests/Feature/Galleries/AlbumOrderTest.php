<?php

namespace Tests\Feature\Galleries;

use App\Models\Album;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlbumOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A gallery with sets "General", "Retratos" and "Fiesta", in that order.
     *
     * @return array{0: Gallery, 1: Album, 2: Album, 3: Album}
     */
    protected function galleryWithThreeSets(): array
    {
        $gallery = Gallery::factory()->create();
        $general = $gallery->albums()->first();
        $portraits = $gallery->albums()->create(['title' => 'Retratos', 'position' => 1]);
        $party = $gallery->albums()->create(['title' => 'Fiesta', 'position' => 2]);

        $this->actingAs($gallery->photographer);

        return [$gallery, $general, $portraits, $party];
    }

    protected function titlesInOrder(Gallery $gallery): array
    {
        return $gallery->albums()->pluck('title')->all();
    }

    public function test_sets_can_be_reordered_by_dragging(): void
    {
        [$gallery, $general, $portraits, $party] = $this->galleryWithThreeSets();

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('saveAlbumOrder', [$party->id, $general->id, $portraits->id]);

        $this->assertSame(['Fiesta', 'General', 'Retratos'], $this->titlesInOrder($gallery));
    }

    public function test_sets_of_another_gallery_are_ignored(): void
    {
        [$gallery, $general, $portraits, $party] = $this->galleryWithThreeSets();
        $foreign = Gallery::factory()->create()->albums()->first();

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('saveAlbumOrder', [$foreign->id, $portraits->id, $general->id, $party->id]);

        $this->assertSame(0, $foreign->refresh()->position);
        $this->assertSame(['Retratos', 'General', 'Fiesta'], $this->titlesInOrder($gallery));
    }

    public function test_a_set_can_be_moved_up_and_down(): void
    {
        [$gallery, , $portraits] = $this->galleryWithThreeSets();

        $component = Livewire::test('pages::galleries.edit', ['gallery' => $gallery]);

        $component->call('moveAlbum', $portraits->id, -1);
        $this->assertSame(['Retratos', 'General', 'Fiesta'], $this->titlesInOrder($gallery));

        $component->call('moveAlbum', $portraits->id, 1);
        $component->call('moveAlbum', $portraits->id, 1);
        $this->assertSame(['General', 'Fiesta', 'Retratos'], $this->titlesInOrder($gallery));
    }

    public function test_a_set_can_be_renamed(): void
    {
        [$gallery, , $portraits] = $this->galleryWithThreeSets();

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('renameAlbum', $portraits->id, '  Retratos de familia  ')
            ->assertHasNoErrors();

        $this->assertSame('Retratos de familia', $portraits->refresh()->title);
    }

    public function test_a_set_name_cannot_be_empty(): void
    {
        [$gallery, , $portraits] = $this->galleryWithThreeSets();

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('renameAlbum', $portraits->id, '   ')
            ->assertHasErrors('albumTitle');

        $this->assertSame('Retratos', $portraits->refresh()->title);
    }

    public function test_sets_of_another_gallery_cannot_be_renamed(): void
    {
        [$gallery] = $this->galleryWithThreeSets();
        $foreign = Gallery::factory()->create()->albums()->first();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('renameAlbum', $foreign->id, 'Hackeado');
    }

    public function test_moving_past_the_ends_does_nothing(): void
    {
        [$gallery, $general, , $party] = $this->galleryWithThreeSets();

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('moveAlbum', $general->id, -1)
            ->call('moveAlbum', $party->id, 1);

        $this->assertSame(['General', 'Retratos', 'Fiesta'], $this->titlesInOrder($gallery));
    }
}
