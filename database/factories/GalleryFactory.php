<?php

namespace Database\Factories;

use App\Enums\GalleryStatus;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->words(3, true);

        return [
            'photographer_id' => User::factory(),
            'title' => $title,
            'client_name' => $this->faker->name(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'unlock_code' => Str::upper(Str::random(8)),
            'location' => $this->faker->optional()->city(),
            'available_until' => null,
            'status' => GalleryStatus::Published,
        ];
    }

    /**
     * A gallery that hasn't been published yet (the default for newly created ones).
     */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => GalleryStatus::Draft]);
    }
}
