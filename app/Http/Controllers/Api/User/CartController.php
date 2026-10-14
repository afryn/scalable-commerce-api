<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartStoreRequest;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;

class CartController extends Controller
{

    public function index()
    {
        return Cart::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CartStoreRequest $request)
    {
        $data = $request->validated();
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id
        ]);

        $item = $cart->items()
            ->where('product_id', $data['product_id'])
            ->first();


        if ($item) {
            $item->increment('quantity', $data['quantity']);

        } else {
            $cart->items()->create([
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
            ]);
        }

        // Load cart items
        $cart->load('items');

        return response()->json([
            'message' => 'Added to cart successfully.',
            'data' => $cart,
        ], 201);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();
        return response()->json(['message'=>'Deleted Successfully']);
    }
}
