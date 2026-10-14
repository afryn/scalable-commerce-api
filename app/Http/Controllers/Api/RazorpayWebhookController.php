<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-Razorpay-Signature');

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            config('services.razorpay.webhook_secret')
        );

        if (
            !$signature ||
            !hash_equals($expectedSignature, $signature)
        ) {
            return response()->json([
                'message' => 'Invalid webhook signature'
            ], 401);
        }

        $event = $request->input('event');

        if ($event === 'refund.processed') {

            $gatewayRefund = $request->input('payload.refund.entity', []);

            if (empty($gatewayRefund['id'])) {
                return response()->json(['message' => 'Missing refund ID'], 422);
            }

            DB::transaction(function () use ($gatewayRefund) {

                $refund = PaymentRefund::where(
                    'gateway_refund_id',
                    $gatewayRefund['id']
                )->lockForUpdate()->first();

                if (!$refund) {
                    Log::warning('Unknown Razorpay refund', [
                        'gateway_refund_id' => $gatewayRefund['id'],
                    ]);
                    return;
                }

                if (
                    ($gatewayRefund['payment_id'] ?? null) !== $refund->gateway_payment_id ||
                    ($gatewayRefund['currency'] ?? null) !== 'INR' ||
                    (int) ($gatewayRefund['amount'] ?? -1) !==
                    (int) round($refund->amount * 100)
                ) {
                    throw new \RuntimeException('Refund details mismatch');
                }

                if (($gatewayRefund['status'] ?? null) === 'processed') {
                    $refund->update([
                        'status' => 'refunded',
                        'submission_status' => 'submitted',
                    ]);
                }
            });

            return response()->json(['message' => 'Refund processed']);
        }

        if ($event == 'payment.failed') {
            $razorpayPayment = $request->input(
                'payload.payment.entity'
            );
            Log::warning('Razorpay payment attempt failed', [
                'gateway_payment_id' => $razorpayPayment['id'] ?? null,
                'gateway_order_id' => $razorpayPayment['order_id'] ?? null,
                'error_code' =>  $razorpayPayment['error_code'] ?? null,
                'error_description' => $razorpayPayment['error_descriptio'] ?? null
            ]);
            return response()->json([
                'message' => 'Failed payment attempt recorded',
            ], 200);
        } elseif ($event == 'payment.captured') {

            $razorpayPayment = $request->input(
                'payload.payment.entity'
            );

            $gatewayOrderId = $razorpayPayment['order_id'] ?? null;
            $gatewayPaymentId = $razorpayPayment['id'] ?? null;

            if (!$gatewayOrderId || !$gatewayPaymentId) {
                return response()->json([
                    'message' => 'Invalid payment method'
                ], 422);
            }

            DB::transaction(function () use (
                $gatewayOrderId,
                $gatewayPaymentId,
                $razorpayPayment
            ) {
                $payment = Payment::where('gateway_order_id', $gatewayOrderId)->lockForUpdate()->firstOrFail();

                // prevent duplicate paymet processing
                if ($payment->status == 'success') {
                    Log::info('Duplicate payment webhook ignored', [
                        'payment_id' => $payment->id,
                        'gateway_payment_id' => $gatewayPaymentId,
                    ]);
                    return; // already processed
                }

                // match payment values
                $rzpStatus = $razorpayPayment['status'] ?? null;
                $rzpAmount = $razorpayPayment['amount'] ?? null;
                $rzpCurrency = $razorpayPayment['currency'] ?? null;

                if (
                    $rzpAmount != (int) round($payment->amount * 100) ||
                    $rzpCurrency != 'INR' ||
                    $rzpStatus != 'captured'
                ) {
                    throw new RuntimeException("Razorpay Payment detaild mismatch");
                }

                // order
                $order = Order::where('id', $payment->order_id)
                    ->lockForUpdate()->firstOrFail();

                // check if order is cancelled or expired
                if (in_array($order->status, ['cancelled', 'expired'], true)) {

                    PaymentRefund::firstOrCreate(
                        [
                            'gateway_payment_id' => $gatewayPaymentId
                        ],
                        [
                            'payment_id' => $payment->id,
                            'amount' => $razorpayPayment['amount'] / 100,
                            'status' => 'pending',
                            'reason' => 'order_expired'
                        ]
                    );

                    $payment->update([
                        'gateway_payment_id' => $gatewayPaymentId,
                        'status' => 'refund_pending'
                    ]);

                    Log::critical("Late payment captured for expired/cancelled order", [
                        'order_id' => $order->id,
                        'gateway_payment_id' => $gatewayPaymentId,
                        'gateway_order_id' => $gatewayOrderId
                    ]);
                    // Do not deduct inventory or fulfill the order.
                    // This payment needs reconciliation and possibly a refund.

                    return;
                }

                // Finalized reserved inventory
                foreach ($order->items()->orderBy('product_id')->get() as $key => $item) {
                    $inventory = Inventory::where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();

                    if (
                        $inventory->reserved_quantity < $item->quantity ||
                        $inventory->quantity < $item->quantity
                    ) {
                        throw new RuntimeException('Inventory reservation mismatch. Reserved : ' .  $inventory->reserved_quantity  . ', Actual qty : ' . $inventory->quantity . ' , Ordered : ' . $item->quantity);
                    }

                    $inventory->decrement('quantity', $item->quantity);
                    $inventory->decrement('reserved_quantity', $item->quantity);
                }

                // clear cart items
                $cart = Cart::with('items.product')
                    ->where('user_id', $order->user_id)
                    ->first();
                $cart->items()->delete();

                //payment update
                $payment->update(['status' => 'success','paid_at' => now(), 'gateway_payment_id' => $gatewayPaymentId]);

                // order update
                $order->update(['payment_status' => 'paid', 'status' => 'confirmed',]);
            });
        }


        Log::info('Razorpay webhook verified', [
            'event' => $request->input('event'),
            'event_id' => $request->header('X-Razorpay-Event-Id'),
        ]);

        return response()->json([
            'message' => 'Webhook received'
        ], 200);
    }
}
