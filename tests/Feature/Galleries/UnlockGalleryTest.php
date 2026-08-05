<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnlockGalleryTest extends TestCase
{
    use RefreshDatabase;

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
