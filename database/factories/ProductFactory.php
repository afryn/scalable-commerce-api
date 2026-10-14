<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => 'SKU-' . strtoupper(Str::random(8)),
            'description' => fake()->paragraph(3),
            'price' => fake()->randomFloat(2, 100, 50000),
            'category_id' => Category::inRandomOrder()->value('id'),
        ];
    }
}
