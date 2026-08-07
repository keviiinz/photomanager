<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotographerCanManageGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographer_can_create_a_gallery_with_a_default_album(): void
    {
        Storage::fake('local');
        $photographer = User::factory()->create();

        $this->actingAs($photographer);

        $response = Livewire::test('pages::galleries.create')
            ->set('title', 'Boda de Ana y Marco')
            ->set('client_name', 'Ana Pérez')
            ->set('unlock_code', 'SECRET1')
            ->call('save');

        $response->assertHasNoErrors();

        $gallery = Gallery::firstOrFail();
        $this->assertSame($photographer->id, $gallery->photographer_id);
        $this->assertSame('Boda de Ana y Marco', $gallery->title);
        $this->assertTrue(Hash::check('SECRET1', $gallery->unlock_code));
        $this->assertCount(1, $gallery->albums);
        $this->assertSame('General', $gallery->albums->first()->title);
        $response->assertSessionHas('revealed_code', 'SECRET1');
    }

    public function test_photographer_can_add_an_album_and_upload_photo_and_video(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);

        $this->actingAs($photographer);

        $component = Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->set('newAlbumTitle', 'Retratos')
            ->call('addAlbum')
            ->assertHasNoErrors();

        $album = $gallery->albums()->where('title', 'Retratos')->firstOrFail();

        $component->set('activeAlbumId', $album->id)
            ->set('newFiles', [
                UploadedFile::fake()->image('foto.jpg'),
                UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            ])
            ->call('uploadFiles')
            ->assertHasNoErrors();

        $this->assertSame(2, $album->media()->count());
        $this->assertSame(1, $album->media()->where('type', 'photo')->count());
        $this->assertSame(1, $album->media()->where('type', 'video')->count());
    }

    public function test_photographer_can_mark_a_photo_as_featured_and_regenerate_code(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);
        $album = $gallery->albums()->first();
        $media = $album->media()->create([
            'type' => 'photo',
            'disk' => 'local',
            'path' => 'galleries/fake.jpg',
            'original_name' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
        ]);

        $this->actingAs($photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('toggleFeatured', $media->id)
            ->call('regenerateCode')
            ->assertHasNoErrors();

        $this->assertTrue($media->fresh()->is_featured);
        $this->assertFalse(Hash::check('anything-old', $gallery->fresh()->unlock_code));
    }

    public function test_regenerated_code_stays_visible_on_screen_until_dismissed(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);

        $this->actingAs($photographer);

        $component = Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('regenerateCode');

        $revealedCode = $component->get('revealedCode');

        $this->assertNotNull($revealedCode);
        $this->assertTrue(Hash::check($revealedCode, $gallery->fresh()->unlock_code));

        $component->call('dismissRevealedCode');

        $this->assertNull($component->get('revealedCode'));
    }

    public function test_photographer_can_choose_a_cover_photo_for_the_gallery(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);
        $album = $gallery->albums()->first();
        $photo = $album->media()->create([
            'type' => 'photo',
            'disk' => 'local',
            'path' => 'galleries/fake.jpg',
            'original_name' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
        ]);

        $this->actingAs($photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('setCover', $photo->id)
            ->assertHasNoErrors();

        $this->assertSame($photo->id, $gallery->fresh()->cover_media_id);
        $this->assertSame($photo->id, $gallery->fresh()->coverImage()->id);
    }

    public function test_gallery_cover_falls_back_to_the_first_featured_photo_when_none_is_chosen(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);
        $album = $gallery->albums()->first();

        $album->media()->create([
            'type' => 'photo', 'disk' => 'local', 'path' => 'galleries/plain.jpg',
            'original_name' => 'plain.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
            'is_featured' => false, 'position' => 0,
        ]);

        $featured = $album->media()->create([
            'type' => 'photo', 'disk' => 'local', 'path' => 'galleries/featured.jpg',
            'original_name' => 'featured.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
            'is_featured' => true, 'position' => 1,
        ]);

        $this->assertNull($gallery->cover_media_id);
        $this->assertSame($featured->id, $gallery->coverImage()->id);
    }

    public function test_a_photographer_cannot_edit_another_photographers_gallery(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $owner->id]);

        $this->actingAs($intruder);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->assertForbidden();
    }
}
