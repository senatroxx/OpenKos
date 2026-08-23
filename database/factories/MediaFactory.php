<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'mediable_type' => Property::class,
            'mediable_id' => Property::factory(),
            'collection' => 'attachments',
            'disk' => (string) config('filesystems.default', 'local'),
            'path' => 'media/'.fake()->uuid().'.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 1024,
            'original_name' => 'attachment.bin',
            'position' => 0,
            'metadata' => null,
        ];
    }
}
