<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'new',
            'email' => fake()->safeEmail(),
            'city' => fake()->city(),
            'street' => fake()->streetName(),
            'designer_processing' => false,
            'amount' => (int) config('orders.base_price'),
            'original_name' => 'photo.jpg',
            'original_image_path' => 'orders/example/original.png',
            'generated_image_path' => 'orders/example/generated.png',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
