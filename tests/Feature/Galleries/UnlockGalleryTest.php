<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UnlockGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_unlock_a_gallery_with_the_correct_code_via_cookie(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);

        Livewire::test('pages::galleries.show', ['gallery' => $gallery])
            ->set('code', 'ABC123')
            ->call('unlock')
            ->assertHasNoErrors();

        $this->assertTrue(Cookie::hasQueued("gallery_unlocked_{$gallery->id}"));
    }

    public function test_guest_gains_access_to_non_featured_media_once_the_unlock_cookie_is_set(): void
    {
        Storage::fake('local');
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();

        // Two teaser slots ahead of it, so this one stays genuinely locked pre-unlock.
        $album->media()->create([
            'type' => 'photo', 'disk' => 'local', 'path' => 'galleries/teaser-one.jpg',
            'original_name' => 'teaser-one.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
            'is_featured' => false, 'position' => 1,
        ]);
        $album->media()->create([
            'type' => 'photo', 'disk' => 'local', 'path' => 'galleries/teaser-two.jpg',
            'original_name' => 'teaser-two.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
            'is_featured' => false, 'position' => 2,
        ]);

        Storage::disk('local')->put('galleries/hidden.jpg', 'fake-bytes');

        $hidden = $album->media()->create([
            'type' => 'photo',
            'disk' => 'local',
            'path' => 'galleries/hidden.jpg',
            'original_name' => 'hidden.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
            'is_featured' => false,
            'position' => 3,
        ]);

        $this->get(route('media.show', $hidden))->assertForbidden();

        $this->withCookie("gallery_unlocked_{$gallery->id}", '1')
            ->get(route('media.show', $hidden))
            ->assertOk();
    }

    public function test_client_can_unlock_a_gallery_with_the_correct_code(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        Livewire::test('pages::galleries.show', ['gallery' => $gallery])
            ->set('code', 'ABC123')
            ->call('unlock')
            ->assertHasNoErrors();

        $this->assertTrue($gallery->fresh()->isUnlockedFor($client));
    }

    public function test_client_cannot_unlock_a_gallery_with_the_wrong_code(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        Livewire::test('pages::galleries.show', ['gallery' => $gallery])
            ->set('code', 'WRONG')
            ->call('unlock')
            ->assertHasErrors('code');

        $this->assertFalse($gallery->fresh()->isUnlockedFor($client));
    }

    public function test_repeated_wrong_codes_are_rate_limited(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        $component = Livewire::test('pages::galleries.show', ['gallery' => $gallery]);

        for ($i = 0; $i < 5; $i++) {
            $component->set('code', 'WRONG')->call('unlock');
        }

        $component->set('code', 'ABC123')->call('unlock')->assertHasErrors('code');

        $this->assertFalse($gallery->fresh()->isUnlockedFor($client));
    }
}
