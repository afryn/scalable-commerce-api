<?php

namespace App\Console\Commands;

use App\Models\PaymentRefund;
use App\Services\RazorpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPendingRefunds extends Command
{
    protected $signature = 'refunds:process';

    protected $description = 'Reconcile pending Razorpay refunds';

    public function handle(RazorpayService $gateway): int
    {
        $refundIds = PaymentRefund::whereIn('status', [
                'pending',
                'processing',
            ])
            ->orderBy('id')
            ->pluck('id');

        foreach ($refundIds as $refundId) {

            $refund = PaymentRefund::find($refundId);

            if (
                !$refund ||
                !in_array($refund->status, ['pending', 'processing'], true)
            ) {
                continue;
            }

            $this->info("Checking refund {$refund->id}");

            // Without a stored gateway refund ID, we cannot
            // reliably identify which Razorpay refund belongs
            // to this local refund request.
            if (!$refund->gateway_refund_id) {
                $this->warn(
                    "Refund {$refund->id} requires manual reconciliation. Gateway refund ID missing."
                );

                continue;
            }

            try {

                // Razorpay API call outside DB transaction
                $response = $gateway->fetchPaymentRefunds(
                    $refund->gateway_payment_id
                );

                $matchedRefund = null;

                foreach ($response['items'] ?? [] as $razorpayRefund) {

                    if (
                        ($razorpayRefund['id'] ?? null) !== $refund->gateway_refund_id ||
                        ($razorpayRefund['payment_id'] ?? null) !== $refund->gateway_payment_id ||
                        ($razorpayRefund['currency'] ?? null) !== 'INR' ||
                        ($razorpayRefund['amount'] ?? null) != (int) round($refund->amount * 100)
                    ) {
                        continue;
                    }

                    $matchedRefund = $razorpayRefund;
                    break;
                }

                if (!$matchedRefund) {
                    $this->warn(
                        "No matching Razorpay refund found for refund {$refund->id}"
                    );

                    continue;
                }

                // Update local database using a short transaction
                DB::transaction(function () use ($refundId, $matchedRefund) {

                    $lockedRefund = PaymentRefund::whereKey($refundId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$lockedRefund ||
                        !in_array(
                            $lockedRefund->status,
                            ['pending', 'processing'],
                            true
                        ) ||
                        $lockedRefund->gateway_refund_id !== $matchedRefund['id'] ||
                        $lockedRefund->gateway_payment_id !== $matchedRefund['payment_id'] ||
                        (int) round($lockedRefund->amount * 100) !== (int) $matchedRefund['amount']
                    ) {
                        return;
                    }

                    $gatewayStatus = $matchedRefund['status'] ?? null;

                    if ($gatewayStatus === 'processed') {

                        $lockedRefund->update([
                            'status' => 'refunded',
                        ]);

                        $this->info(
                            "Refund {$lockedRefund->id} completed successfully."
                        );

                    } else {

                        $this->warn(
                            "Refund {$lockedRefund->id} gateway status: {$gatewayStatus}"
                        );
                    }
                });

            } catch (Throwable $e) {

                Log::error('Razorpay refund reconciliation failed', [
                    'refund_id' => $refundId,
                    'error' => $e->getMessage(),
                ]);

                $this->error(
                    "Failed to reconcile refund {$refundId}: {$e->getMessage()}"
                );
            }
        }

        return self::SUCCESS;
    }
}
