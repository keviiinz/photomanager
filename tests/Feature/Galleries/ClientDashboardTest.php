<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dashboard_only_lists_galleries_the_client_has_saved(): void
    {
        $client = User::factory()->client()->create();

        $saved = Gallery::factory()->create(['title' => 'Saved Session']);
        $saved->saveFor($client);

        $notSaved = Gallery::factory()->create(['title' => 'Other Session']);

        $this->actingAs($client);

        $response = $this->get(route('galleries.my'));

        $response->assertOk()
            ->assertSee('Saved Session')
            ->assertDontSee('Other Session');
    }

    public function test_client_can_add_a_gallery_to_their_collection_by_slug_without_a_code(): void
    {
        $client = User::factory()->client()->create();
        $gallery = Gallery::factory()->create();

        $this->actingAs($client);

        Livewire::test('pages::galleries.my')
            ->set('slug', $gallery->slug)
            ->call('addGallery')
            ->assertHasNoErrors();

        $this->assertTrue($gallery->fresh()->isSavedFor($client));
        $this->assertFalse($gallery->fresh()->isUnlockedFor($client));
    }

    public function test_client_can_add_a_gallery_by_pasting_the_full_public_link(): void
    {
        $client = User::factory()->client()->create();
        $gallery = Gallery::factory()->create();

        $this->actingAs($client);

        Livewire::test('pages::galleries.my')
            ->set('slug', "http://127.0.0.1:8000/g/{$gallery->slug}")
            ->call('addGallery')
            ->assertHasNoErrors();

        $this->assertTrue($gallery->fresh()->isSavedFor($client));
    }

    public function test_client_cannot_add_the_same_gallery_twice(): void
    {
        $client = User::factory()->client()->create();
        $gallery = Gallery::factory()->create();
        $gallery->saveFor($client);

        $this->actingAs($client);

        Livewire::test('pages::galleries.my')
            ->set('slug', $gallery->slug)
            ->call('addGallery')
            ->assertHasErrors('slug');
    }

    public function test_a_photographer_cannot_access_the_client_dashboard(): void
    {
        $photographer = User::factory()->create();

        $this->actingAs($photographer)
            ->get(route('galleries.my'))
            ->assertForbidden();
    }
}
