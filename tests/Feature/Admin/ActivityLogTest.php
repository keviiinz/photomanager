<?php

namespace Tests\Feature\Admin;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_the_activity_log(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->get(route('admin.activity-log'))
            ->assertOk();
    }

    public function test_a_photographer_cannot_view_the_activity_log(): void
    {
        $photographer = User::factory()->create();

        $this->actingAs($photographer)
            ->get(route('admin.activity-log'))
            ->assertForbidden();
    }

    public function test_a_client_cannot_view_the_activity_log(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('admin.activity-log'))
            ->assertForbidden();
    }

    public function test_creating_a_gallery_is_logged(): void
    {
        $photographer = User::factory()->create();
        $this->actingAs($photographer);

        Livewire::test('pages::galleries.create')
            ->set('title', 'Boda de Ana y Marco')
            ->set('client_name', 'Ana Pérez')
            ->set('unlock_code', 'SECRET1')
            ->call('save');

        $this->assertTrue(
            Activity::where('log_name', 'gallery')
                ->where('event', 'created')
                ->where('causer_id', $photographer->id)
                ->exists()
        );
    }

    public function test_deleting_a_gallery_is_logged(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);
        $this->actingAs($photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->call('deleteGallery');

        $this->assertTrue(
            Activity::where('log_name', 'gallery')
                ->where('event', 'deleted')
                ->where('causer_id', $photographer->id)
                ->exists()
        );
    }

    public function test_uploading_media_is_logged(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $photographer = User::factory()->create();
        $gallery = Gallery::factory()->create(['photographer_id' => $photographer->id]);
        $album = $gallery->albums()->first();
        $this->actingAs($photographer);

        Livewire::test('pages::galleries.edit', ['gallery' => $gallery])
            ->set('activeAlbumId', $album->id)
            ->set('newFiles', [UploadedFile::fake()->image('foto.jpg')])
            ->call('uploadFiles');

        $this->assertTrue(
            Activity::where('log_name', 'media')
                ->where('event', 'created')
                ->where('causer_id', $photographer->id)
                ->exists()
        );
    }

    public function test_a_successful_unlock_is_logged(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        Livewire::test('pages::galleries.show', ['gallery' => $gallery])
            ->set('code', 'ABC123')
            ->call('unlock');

        $this->assertTrue(
            Activity::where('log_name', 'gallery')
                ->where('event', 'unlocked')
                ->where('causer_id', $client->id)
                ->where('subject_id', $gallery->id)
                ->exists()
        );
    }

    public function test_a_failed_unlock_attempt_is_logged(): void
    {
        $gallery = Gallery::factory()->create(['unlock_code' => 'ABC123']);
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        Livewire::test('pages::galleries.show', ['gallery' => $gallery])
            ->set('code', 'WRONG')
            ->call('unlock');

        $this->assertTrue(
            Activity::where('log_name', 'gallery')
                ->where('event', 'unlock_failed')
                ->where('causer_id', $client->id)
                ->exists()
        );
    }
}
