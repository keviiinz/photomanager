<?php

namespace Tests\Feature\Galleries;

use App\Enums\GalleryStatus;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_galleries_start_as_drafts(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::galleries.create')
            ->set('title', 'Sesión')
            ->set('client_name', 'Ana')
            ->set('unlock_code', 'ABC123')
            ->call('save');

        $this->assertSame(GalleryStatus::Draft, Gallery::firstOrFail()->status);
    }

    public function test_drafts_are_not_found_for_anyone_but_the_photographer(): void
    {
        $gallery = Gallery::factory()->draft()->create();

        $this->get(route('galleries.show', $gallery))->assertNotFound();
        $this->actingAs(User::factory()->client()->create())->get(route('galleries.show', $gallery))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('galleries.show', $gallery))->assertNotFound();

        $this->actingAs($gallery->photographer)->get(route('galleries.show', $gallery))->assertOk();
    }

    public function test_media_of_a_draft_is_not_accessible_to_visitors(): void
    {
        $gallery = Gallery::factory()->draft()->create();
        $featured = Media::factory()->featured()->create(['album_id' => $gallery->albums()->first()->id]);

        $this->assertFalse($featured->isViewableBy(null));
        $this->assertTrue($featured->isViewableBy($gallery->photographer));
        $this->get(route('media.download', $featured))->assertNotFound();
    }

    public function test_publishing_makes_the_gallery_visible(): void
    {
        $gallery = Gallery::factory()->draft()->create();

        $this->actingAs($gallery->photographer);
        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('setStatus', 'published');

        $this->assertSame(GalleryStatus::Published, $gallery->refresh()->status);

        auth()->logout();
        $this->get(route('galleries.show', $gallery))->assertOk();
    }

    public function test_a_published_gallery_can_go_back_to_draft(): void
    {
        $gallery = Gallery::factory()->create();

        $this->actingAs($gallery->photographer);
        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('setStatus', 'draft');

        $this->assertSame(GalleryStatus::Draft, $gallery->refresh()->status);
    }

    public function test_clients_cannot_add_a_draft_to_their_collection(): void
    {
        $gallery = Gallery::factory()->draft()->create();
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        Livewire::test('pages::galleries.my')
            ->set('slug', $gallery->slug)
            ->call('addGallery')
            ->assertHasErrors('slug');

        $this->assertFalse($gallery->isSavedFor($client));
    }

    public function test_saved_galleries_that_go_back_to_draft_disappear_from_the_client_list(): void
    {
        $gallery = Gallery::factory()->create();
        $client = User::factory()->client()->create();
        $gallery->saveFor($client);

        $this->actingAs($client);
        $this->assertCount(1, Livewire::test('pages::galleries.my')->get('galleries'));

        $gallery->update(['status' => GalleryStatus::Draft]);
        $this->assertCount(0, Livewire::test('pages::galleries.my')->get('galleries'));
    }
}
