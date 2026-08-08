<?php

namespace Tests\Feature\Admin;

use App\Models\HomeImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HomeImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_the_home_images_page(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->get(route('admin.home-images'))
            ->assertOk();
    }

    public function test_a_photographer_cannot_view_the_home_images_page(): void
    {
        $photographer = User::factory()->create();

        $this->actingAs($photographer)
            ->get(route('admin.home-images'))
            ->assertForbidden();
    }

    public function test_the_first_uploaded_image_becomes_primary_automatically(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        Livewire::test('pages::admin.home-images')
            ->set('newImages', [UploadedFile::fake()->image('foto.jpg')])
            ->call('uploadImages')
            ->assertHasNoErrors();

        $image = HomeImage::firstOrFail();
        $this->assertTrue($image->is_primary);
        $this->assertSame('local', $image->disk);
        Storage::disk('local')->assertExists($image->path);
    }

    public function test_superadmin_can_change_the_primary_image(): void
    {
        $first = HomeImage::factory()->primary()->create(['position' => 0]);
        $second = HomeImage::factory()->create(['position' => 1]);

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        Livewire::test('pages::admin.home-images')
            ->call('setPrimary', $second->id)
            ->assertHasNoErrors();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_superadmin_can_reorder_images(): void
    {
        $first = HomeImage::factory()->create(['position' => 0]);
        $second = HomeImage::factory()->create(['position' => 1]);
        $third = HomeImage::factory()->create(['position' => 2]);

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        Livewire::test('pages::admin.home-images')
            ->call('saveOrder', [$third->id, $first->id, $second->id])
            ->assertHasNoErrors();

        $this->assertSame(0, $third->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
    }

    public function test_deleting_the_primary_image_promotes_the_next_one(): void
    {
        Storage::fake('local');

        $first = HomeImage::factory()->primary()->create(['position' => 0, 'disk' => 'local']);
        $second = HomeImage::factory()->create(['position' => 1, 'disk' => 'local']);

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        Livewire::test('pages::admin.home-images')
            ->call('deleteImage', $first->id)
            ->assertHasNoErrors();

        $this->assertNull(HomeImage::find($first->id));
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_home_page_shows_the_primary_image_in_the_intro_and_all_images_in_the_carousel(): void
    {
        Storage::fake('local');

        HomeImage::factory()->create(['position' => 0, 'disk' => 'local']);
        $primary = HomeImage::factory()->primary()->create(['position' => 1, 'disk' => 'local']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('home-images.show', $primary), false);
    }
}
