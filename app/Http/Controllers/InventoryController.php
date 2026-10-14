<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function addStock(Product $product, Request $request)
    {
        $request->validate(['quantity' => 'required|integer|min:1']);
        $product->inventory()->firstOrCreate([
            'product_id' => $product->id
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0
        ]);

        $product->inventory()->increment('quantity', $request->quantity);

        $product->load('inventory');
        return success('Stock updated successfully', $product);
    }
}
