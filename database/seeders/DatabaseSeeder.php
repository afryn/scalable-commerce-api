<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(10)->create();
        Category::factory(10)->create();
        Product::factory(10)->hasInventory()->create();
        // Order::factory(10)->hasItems(3)->create();
        // User::all()->each(function ($user) {

        //     $cart = Cart::factory()
        //         ->for($user)
        //         ->create();

        //     $products = Product::inRandomOrder()
        //         ->limit(3)
        //         ->get();

        //     foreach ($products as $product) {
        //         CartItem::factory()
        //             ->for($cart)
        //             ->for($product)
        //             ->create();
        //     }
        // });
    }
}
