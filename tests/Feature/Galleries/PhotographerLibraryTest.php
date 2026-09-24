<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhotographerLibraryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A photo in a new gallery owned by the given photographer.
     */
    protected function photoFor(User $photographer, array $attributes = []): Media
    {
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);

        return Media::factory()->create(['album_id' => $gallery->albums()->first()->id, ...$attributes]);
    }

    public function test_library_and_featured_pages_render_for_photographers(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('galleries.library'))->assertOk()->assertSee('Biblioteca');
        $this->get(route('galleries.featured'))->assertOk()->assertSee('Destacados');
    }

    public function test_clients_cannot_open_the_library(): void
    {
        $this->actingAs(User::factory()->client()->create());

        $this->get(route('galleries.library'))->assertForbidden();
        $this->get(route('galleries.featured'))->assertForbidden();
    }

    public function test_library_lists_media_from_every_gallery_of_the_photographer_only(): void
    {
        $photographer = User::factory()->create();
        $first = $this->photoFor($photographer);
        $second = $this->photoFor($photographer);
        $someoneElses = $this->photoFor(User::factory()->create());

        $this->actingAs($photographer);

        $ids = Livewire::test('pages::galleries.library')->get('media')->pluck('id');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids->all());
        $this->assertNotContains($someoneElses->id, $ids);
    }

    public function test_featured_view_only_lists_featured_media(): void
    {
        $photographer = User::factory()->create();
        $featured = $this->photoFor($photographer, ['is_featured' => true]);
        $this->photoFor($photographer);

        $this->actingAs($photographer);

        $ids = Livewire::test('pages::galleries.featured')->get('media')->pluck('id');

        $this->assertSame([$featured->id], $ids->all());
    }

    public function test_photographer_can_toggle_featured_from_the_library(): void
    {
        $photographer = User::factory()->create();
        $photo = $this->photoFor($photographer);

        $this->actingAs($photographer);

        Livewire::test('pages::galleries.library')->call('toggleFeatured', $photo->id);
        $this->assertTrue($photo->refresh()->is_featured);

        Livewire::test('pages::galleries.featured')->call('toggleFeatured', $photo->id);
        $this->assertFalse($photo->refresh()->is_featured);
    }

    public function test_photographer_cannot_toggle_someone_elses_media(): void
    {
        $photo = $this->photoFor(User::factory()->create());

        $this->actingAs(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('pages::galleries.library')->call('toggleFeatured', $photo->id);
    }

    public function test_galleries_can_be_searched_and_shown_as_a_list(): void
    {
        $photographer = User::factory()->create();
        Gallery::factory()->create(['photographer_id' => $photographer->id, 'title' => 'Boda de Ana', 'client_name' => 'Ana']);
        Gallery::factory()->create(['photographer_id' => $photographer->id, 'title' => 'Bautizo', 'client_name' => 'Luis Ramos']);

        $this->actingAs($photographer);

        $component = Livewire::test('pages::galleries.index');
        $this->assertCount(2, $component->get('galleries'));

        $component->set('search', 'ramos');
        $this->assertSame(['Bautizo'], $component->get('galleries')->pluck('title')->all());

        $component->call('setView', 'list')->assertSet('view', 'list');
        $component->call('setView', 'nonsense')->assertSet('view', 'grid');
    }
}
