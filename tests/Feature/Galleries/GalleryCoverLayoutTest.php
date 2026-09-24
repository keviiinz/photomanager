<?php

namespace Tests\Feature\Galleries;

use App\Enums\CoverLayout;
use App\Models\Gallery;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryCoverLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_galleries_use_the_center_layout(): void
    {
        $this->assertSame(CoverLayout::Center, Gallery::factory()->create()->cover_layout);
    }

    public function test_editor_renders_with_every_layout_option(): void
    {
        $gallery = Gallery::factory()->create();

        $response = $this->actingAs($gallery->photographer)->get(route('galleries.edit', $gallery));

        $response->assertOk();
        foreach (CoverLayout::cases() as $layout) {
            $response->assertSee($layout->label());
        }
    }

    public function test_photographer_can_change_the_cover_layout(): void
    {
        $gallery = Gallery::factory()->create();

        $this->actingAs($gallery->photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('setCoverLayout', 'novel');

        $this->assertSame(CoverLayout::Novel, $gallery->refresh()->cover_layout);
    }

    public function test_unknown_layouts_are_rejected(): void
    {
        $gallery = Gallery::factory()->create();

        $this->actingAs($gallery->photographer);

        $this->expectException(\ValueError::class);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])->call('setCoverLayout', 'nope');
    }

    public function test_public_page_renders_the_chosen_layout(): void
    {
        $gallery = Gallery::factory()->create(['cover_layout' => CoverLayout::Stripe]);
        Media::factory()->featured()->create(['album_id' => $gallery->albums()->first()->id]);

        $this->get(route('galleries.show', $gallery))
            ->assertOk()
            ->assertSee('data-cover-layout="stripe"', false);
    }
}
