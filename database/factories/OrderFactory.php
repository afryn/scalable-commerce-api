<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
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
            'user_id' => User::inRandomOrder()->value('id'),
            'order_id' => 'ORD_' . Str::ulid(),
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'payment_method' =>  fake()->randomElement(['cod', 'online']),
            'shipping_charge' => 0,
            'total' => 0,
            'status' => fake()->randomElement([
                'pending',
                'confirmed',
                'cancelled',
                'delivered'
            ]),
        ];
    }
}
