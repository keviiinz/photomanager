<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Album;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            'type' => MediaType::Photo,
            'disk' => 'local',
            'path' => 'galleries/'.Str::random(20).'.jpg',
            'original_name' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(50_000, 8_000_000),
            'is_featured' => false,
            'position' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Video,
            'path' => 'galleries/'.Str::random(20).'.mp4',
            'original_name' => $this->faker->word().'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => $this->faker->numberBetween(2_000_000, 80_000_000),
        ]);
    }
}
