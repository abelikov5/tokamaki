<?php

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'browser_id' => (string) Str::uuid(),
            'first_ip_address' => fake()->ipv4(),
            'last_ip_address' => fake()->ipv4(),
            'first_user_agent' => fake()->userAgent(),
            'last_user_agent' => fake()->userAgent(),
            'last_seen_at' => now(),
        ];
    }
}
