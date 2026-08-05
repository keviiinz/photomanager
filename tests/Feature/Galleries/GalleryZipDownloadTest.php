<?php

namespace Tests\Feature\Galleries;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GalleryZipDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocked_client_can_download_a_zip_of_selected_media(): void
    {
        Storage::fake('local');

        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();
        $client = User::factory()->client()->create();
        $gallery->unlockFor($client);

        Storage::disk('local')->put('galleries/one.jpg', 'contents-one');
        Storage::disk('local')->put('galleries/two.jpg', 'contents-two');

        $one = $album->media()->create($this->fakeMediaAttributes(['path' => 'galleries/one.jpg', 'original_name' => 'one.jpg']));
        $two = $album->media()->create($this->fakeMediaAttributes(['path' => 'galleries/two.jpg', 'original_name' => 'two.jpg']));

        $this->actingAs($client);

        $response = $this->post(route('galleries.download-selection', $gallery), [
            'media_ids' => [$one->id, $two->id],
        ]);

        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $this->assertSame(2, $zip->count());
        $zip->close();
    }

    public function test_a_media_id_from_another_gallery_is_silently_excluded_from_the_zip(): void
    {
        Storage::fake('local');

        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();
        $client = User::factory()->client()->create();
        $gallery->unlockFor($client);

        $otherGallery = Gallery::factory()->create();
        $otherAlbum = $otherGallery->albums()->first();

        Storage::disk('local')->put('galleries/mine.jpg', 'contents-mine');
        Storage::disk('local')->put('galleries/other.jpg', 'contents-other');

        $mine = $album->media()->create($this->fakeMediaAttributes(['path' => 'galleries/mine.jpg', 'original_name' => 'mine.jpg']));
        $other = $otherAlbum->media()->create($this->fakeMediaAttributes(['path' => 'galleries/other.jpg', 'original_name' => 'other.jpg']));

        $this->actingAs($client);

        $response = $this->post(route('galleries.download-selection', $gallery), [
            'media_ids' => [$mine->id, $other->id],
        ]);

        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $this->assertSame(1, $zip->count());
        $this->assertSame('mine.jpg', $zip->getNameIndex(0));
        $zip->close();
    }

    public function test_a_client_who_has_not_unlocked_the_gallery_cannot_download(): void
    {
        $gallery = Gallery::factory()->create();
        $album = $gallery->albums()->first();
        $client = User::factory()->client()->create();
        $media = $album->media()->create($this->fakeMediaAttributes());

        $this->actingAs($client);

        $this->post(route('galleries.download-selection', $gallery), [
            'media_ids' => [$media->id],
        ])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function fakeMediaAttributes(array $overrides = []): array
    {
        return array_merge([
            'type' => 'photo',
            'disk' => 'local',
            'path' => 'galleries/fake-'.uniqid().'.jpg',
            'original_name' => 'fake.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
        ], $overrides);
    }
}
