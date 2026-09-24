<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_without_a_date_never_expires(): void
    {
        $gallery = Gallery::factory()->create(['available_until' => null]);

        $this->assertFalse($gallery->isExpired());
        $this->get(route('galleries.show', $gallery))->assertOk();
    }

    public function test_gallery_is_still_available_on_its_last_day(): void
    {
        $gallery = Gallery::factory()->create(['available_until' => today()]);

        $this->assertFalse($gallery->isExpired());
        $this->get(route('galleries.show', $gallery))->assertOk();
    }

    public function test_expired_gallery_is_gone_for_visitors_but_not_for_its_photographer(): void
    {
        $gallery = Gallery::factory()->create(['available_until' => today()->subDay()]);

        $this->get(route('galleries.show', $gallery))->assertStatus(410);
        $this->actingAs(User::factory()->client()->create())->get(route('galleries.show', $gallery))->assertStatus(410);

        $this->actingAs($gallery->photographer)->get(route('galleries.show', $gallery))->assertOk();
    }

    public function test_media_of_an_expired_gallery_cannot_be_viewed_by_visitors(): void
    {
        $gallery = Gallery::factory()->create(['available_until' => today()->subDay()]);
        $featured = Media::factory()->featured()->create(['album_id' => $gallery->albums()->first()->id]);

        $this->assertFalse($featured->isViewableBy(null));
        $this->assertTrue($featured->isViewableBy($gallery->photographer));
        $this->get(route('media.download', $featured))->assertStatus(410);
    }

    public function test_clearing_the_date_stores_null(): void
    {
        $gallery = Gallery::factory()->create(['available_until' => today()->addWeek()]);

        $this->actingAs($gallery->photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->set('available_until', '')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertNull($gallery->refresh()->available_until);
    }

    public function test_new_gallery_without_a_date_is_created_without_expiry(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::galleries.create')
            ->set('title', 'Sesión')
            ->set('client_name', 'Leonardo Carranza')
            ->set('unlock_code', '123456')
            ->set('available_until', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Gallery::firstOrFail()->available_until);
    }
}
