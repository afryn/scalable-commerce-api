<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function initiate(
        int $orderId,
        PaymentGatewayInterface $gateway
    ) {
        $user = request()->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // STEP 1: Claim initiation or reuse an existing gateway order.
        $result = DB::transaction(function () use ($orderId, $user) {

            $payment = Payment::where('order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return ['error' => 'Payment not found', 'code' => 404];
            }

            $order = Order::whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (
                !$order ||
                $order->user_id !== $user->id ||
                $order->payment_method !== 'online'
            ) {
                return ['error' => 'Order not found', 'code' => 404];
            }

            if (
                $order->status !== 'pending' ||
                $order->payment_status !== 'pending' ||
                $payment->status !== 'pending'
            ) {
                return [
                    'error' => 'This order is no longer payable.',
                    'code' => 409,
                ];
            }

            if ($order->expires_at?->isPast()) {
                return [
                    'error' => 'This order is expired. Please place order again.',
                    'code' => 409,
                ];
            }

            $amountInPaise = (int) round($payment->amount * 100);

            // Existing Razorpay order: reuse it.
            if ($payment->gateway_order_id) {
                return [
                    'existing' => true,
                    'gateway_order_id' => $payment->gateway_order_id,
                    'amount' => $amountInPaise,
                ];
            }

            // Another request may be creating a Razorpay order.
            if ($payment->initiation_status !== 'not_started') {
                return [
                    'error' => 'Payment initiation is in progress or requires reconciliation.',
                    'code' => 409,
                ];
            }

            $payment->update([
                'initiation_status' => 'initiating',
            ]);

            return [
                'existing' => false,
                'payment_id' => $payment->id,
                'receipt' => (string) $order->order_id,
                'amount' => $amountInPaise,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
            ], $result['code']);
        }

        if ($result['existing']) {
            return response()->json([
                'key' => config('services.razorpay.key_id'),
                'gateway_order_id' => $result['gateway_order_id'],
                'amount' => $result['amount'],
                'currency' => 'INR',
            ]);
        }

        // STEP 2: Create Razorpay order outside the DB transaction.
        try {
            $razorpayOrder = $gateway->createPayment(
                $result['amount'],
                $result['receipt']
            );

            if (
                empty($razorpayOrder['id']) ||
                (int) ($razorpayOrder['amount'] ?? -1) !== $result['amount'] ||
                ($razorpayOrder['currency'] ?? null) !== 'INR'
            ) {
                throw new \RuntimeException(
                    'Unexpected Razorpay order response'
                );
            }

        } catch (Throwable $e) {
            // Do not reset initiation_status automatically:
            // Razorpay may have created the order despite a timeout.
            Log::error('Razorpay order initiation uncertain', [
                'order_id' => $orderId,
                'payment_id' => $result['payment_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment initiation could not be confirmed. Please contact support.',
            ], 503);
        }

        // STEP 3: Persist the gateway order ID.
        try {
            $saved = DB::transaction(function () use (
                $orderId,
                $result,
                $razorpayOrder
            ) {
                $payment = Payment::whereKey($result['payment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $order = Order::whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $payment->initiation_status !== 'initiating' ||
                    $payment->gateway_order_id !== null ||
                    $payment->status !== 'pending' ||
                    $order->status !== 'pending' ||
                    $order->payment_status !== 'pending'
                ) {
                    return false;
                }

                $payment->update([
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $razorpayOrder['id'],
                    'initiation_status' => 'initiated',
                ]);

                return true;
            });

        } catch (Throwable $e) {
            Log::error('Failed to save Razorpay order', [
                'order_id' => $orderId,
                'gateway_order_id' => $razorpayOrder['id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment initiation requires reconciliation.',
            ], 503);
        }

        if (!$saved) {
            return response()->json([
                'message' => 'Payment state changed. Reconciliation required.',
            ], 409);
        }

        // STEP 4: Return Razorpay Checkout details.
        return response()->json([
            'key' => config('services.razorpay.key_id'),
            'gateway_order_id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
        ]);
    }
}
