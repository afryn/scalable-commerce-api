<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderPlaceRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Order::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrderPlaceRequest $request)
    {

        $data = $request->validated();
        $userId = auth()->id();

        // get user cart with products
        $cart = Cart::with('items.product')
            ->where('user_id', $userId)
            ->first();

        // check if cart exists
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        // check if cart is empty
        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        // calculations
        $subtotal = $cart->items->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        $discount =  $tax =  $shippingCharge = 0;

        $total = $subtotal
            - $discount
            + $tax
            + $shippingCharge;

        DB::beginTransaction();
        try {
            // create order
            $order = Order::create([
                'order_id' => 'ORD_' .  Str::ulid(),
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'payment_method' => $data['payment_method'],
                'shipping_charge' => $shippingCharge,
                'total' => $total,
                 'expires_at' => now()->addMinutes(15),
            ]);
            // create order items
            foreach ($cart->items as  $cartItem) {
                // lock inventory row for stock update
                $inventory =  Inventory::where('product_id', $cartItem->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // check available stock
                $availableStock = $inventory->quantity - $inventory->reserved_quantity;

                if ($availableStock < $cartItem->quantity) {
                    exception($cartItem->product->name . ' has only ' . $availableStock . ' qunatity. Please update cart to proceed.', 409);
                }

                if ($order->payment_method == 'cod') {
                    // decrement stock immediately
                    $inventory->decrement('quantity', $cartItem->quantity);
                } else {
                    // Reserve stock
                    $inventory->increment('reserved_quantity', $cartItem->quantity);
                }

                $unit_price = $cartItem->product->price;
                $itemTotal = $unit_price * $cartItem->quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->name,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $unit_price,
                    'total' => $itemTotal,
                ]);
            }

            // delete items from cart
            if ($order->payment_method === 'cod') {
                $cart->items()->delete();
            }

            // create order payment
            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'razorpay',
                'amount' => $total,
                'payment_method' => 'online',
                'status' => 'pending',
            ]);

            //  Commit transaction
            DB::commit();

            // return response
            return response()->json([
                'message' => 'Order placed',
                'order' => $order->load('items'),
            ], 201);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            DB::rollBack();
            return $e->getResponse();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'message' => 'Order could not be placed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return $order;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OrderUpdateRequest $request, Order $order)
    {
        $data = $request->validated();
        $data['status'] = 'cancelled';
        $order->update($data);
        return $order;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['msg' => 'Deleted Successfully']);
    }
}
