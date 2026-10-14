<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire unpaid orders safely';

    public function handle(): int
    {
        $orderIds = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where('payment_method', 'online')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subMinutes(10))
            ->orderBy('id')
            ->pluck('id');

        foreach ($orderIds as $orderId) {
            try {
                $expired = DB::transaction(function () use ($orderId) {

                    // Lock payment first, then order.
                    // Keep this order consistent with PaymentController.
                    $payment = Payment::where('order_id', $orderId)
                        ->lockForUpdate()
                        ->first();

                    if (!$payment) {
                        Log::warning('Expired order missing payment', [
                            'order_id' => $orderId,
                        ]);

                        return false;
                    }

                    $order = Order::whereKey($orderId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$order ||
                        $order->status !== 'pending' ||
                        $order->payment_status !== 'pending' ||
                        $payment->status !== 'pending' ||
                        !$order->expires_at ||
                        $order->expires_at->gt(now()->subMinutes(10))
                    ) {
                        return false;
                    }

                    // Razorpay order already exists.
                    // Payment might still be completed.
                    if ($payment->gateway_order_id !== null) {
                        $this->warn(
                            "Order {$orderId}: Gateway reconciliation required."
                        );

                        return false;
                    }

                    // Payment initiation may be in progress,
                    // completed, or have an uncertain outcome.
                    if ($payment->initiation_status !== 'not_started') {
                        $this->warn(
                            "Order {$orderId}: Payment initiation requires reconciliation."
                        );

                        return false;
                    }

                    // Group duplicate product rows.
                    $items = $order->items()
                        ->selectRaw(
                            'product_id, SUM(quantity) as total_quantity'
                        )
                        ->groupBy('product_id')
                        ->orderBy('product_id')
                        ->get();

                    foreach ($items as $item) {
                        $inventory = Inventory::where(
                            'product_id',
                            $item->product_id
                        )
                            ->lockForUpdate()
                            ->firstOrFail();

                        $quantity = (int) $item->total_quantity;

                        if ($inventory->reserved_quantity < $quantity) {
                            throw new RuntimeException(
                                "Inventory reservation mismatch for product {$item->product_id}"
                            );
                        }

                        // Release reserved stock only.
                        // Do not reduce physical quantity.
                        $inventory->decrement(
                            'reserved_quantity',
                            $quantity
                        );
                    }

                    $order->update([
                        'status' => 'expired',
                    ]);

                    return true;
                });

                if ($expired) {
                    $this->info(
                        "Order {$orderId} expired. Inventory reservation released."
                    );
                }

            } catch (Throwable $e) {
                Log::error('Order expiration failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

                $this->error(
                    "Order {$orderId}: {$e->getMessage()}"
                );
            }
        }

        return self::SUCCESS;
    }
}
