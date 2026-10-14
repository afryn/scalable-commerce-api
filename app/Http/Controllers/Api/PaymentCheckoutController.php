<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentCheckoutController extends Controller
{
    public function show(Request $request, int $orderId)
    {
        // temporary local development page
        abort_unless(app()->environment('local'), 404);

        $order = Order::whereKey($orderId)
            ->where('payment_method', 'online')
            ->firstOrFail();

        $payment = Payment::where('order_id', $order->id)
            ->where('status', 'pending')
            ->whereNotNull('gateway_order_id')
            ->firstOrFail();

        return view('payments.checkout', [
            'key' => config('services.razorpay.key_id'),
            'gatewayOrderId' => $payment->gateway_order_id,
            'amount' => (int) round($payment->amount * 100),
            'currency' => 'INR',
        ]);
    }
}
