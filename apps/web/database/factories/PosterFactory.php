<?php

namespace Database\Factories;

use App\Models\Poster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Poster> */
class PosterFactory extends Factory
{
    protected $model = Poster::class;

    public function definition(): array
    {
        $nmId = (string) fake()->unique()->numberBetween(100000000, 999999999);
        $title = fake()->sentence(3);

        return [
            'source_user_id' => 1,
            'source_key' => 'item:'.$nmId,
            'nm_id' => $nmId,
            'slug' => Str::slug($title).'-'.$nmId,
            'status' => 'draft',
            'title' => $title,
            'description' => fake()->paragraph(),
            'category' => 'Абстракция',
            'orientation' => 'vertical',
            'imported_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
