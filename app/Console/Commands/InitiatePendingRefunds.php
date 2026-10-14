<?php

namespace App\Console\Commands;

use App\Models\PaymentRefund;
use App\Services\RazorpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InitiatePendingRefunds extends Command
{
    protected $signature = 'refunds:initiate
        {--execute : Submit eligible refunds to Razorpay}
        {--id= : Process a specific refund ID}';

    protected $description = 'Initiate pending Razorpay refunds';

    public function handle(RazorpayService $gateway): int
    {
        $query = PaymentRefund::where('status', 'pending')
            ->where('submission_status', 'not_submitted')
            ->whereNull('gateway_refund_id')
            ->whereNull('request_started_at');

        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }

        $refundIds = $query->orderBy('id')->pluck('id');

        if (!$this->option('execute')) {
            $this->info("Eligible refunds: {$refundIds->count()}");
            $this->warn('Dry run only. No refunds submitted.');

            return self::SUCCESS;
        }

        foreach ($refundIds as $refundId) {

            $refund = DB::transaction(function () use ($refundId) {

                $refund = PaymentRefund::whereKey($refundId)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$refund ||
                    $refund->status !== 'pending' ||
                    $refund->submission_status !== 'not_submitted' ||
                    $refund->gateway_refund_id !== null ||
                    $refund->request_started_at !== null
                ) {
                    return null;
                }

                $refund->update([
                    'status' => 'processing',
                    'submission_status' => 'submitting',
                    'request_key' => (string) Str::uuid(),
                    'request_started_at' => now(),
                    'submitted_at' => now(),
                ]);

                return $refund->fresh();
            });

            if (!$refund) {
                continue;
            }

            try {
                $response = $gateway->refund(
                    $refund->gateway_payment_id,
                    (int) round($refund->amount * 100),
                    'refund_' . $refund->id
                );

                if (
                    empty($response['id']) ||
                    ($response['payment_id'] ?? null) !== $refund->gateway_payment_id ||
                    (int) ($response['amount'] ?? -1) !==
                        (int) round($refund->amount * 100)
                ) {
                    throw new \RuntimeException(
                        'Razorpay refund response mismatch'
                    );
                }

                DB::transaction(function () use ($refundId, $response) {

                    $refund = PaymentRefund::whereKey($refundId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $refund->submission_status !== 'submitting' ||
                        $refund->gateway_refund_id !== null
                    ) {
                        throw new \RuntimeException(
                            'Refund state changed unexpectedly'
                        );
                    }

                    $refund->update([
                        'gateway_refund_id' => $response['id'],
                        'submission_status' => 'submitted',
                        'status' => ($response['status'] ?? null) === 'processed'
                            ? 'refunded'
                            : 'processing',
                    ]);
                });

                $this->info("Refund {$refundId} submitted.");

            } catch (Throwable $e) {

                DB::transaction(function () use ($refundId) {

                    $refund = PaymentRefund::whereKey($refundId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $refund &&
                        $refund->submission_status === 'submitting'
                    ) {
                        $refund->update([
                            'submission_status' => 'needs_review',
                        ]);
                    }
                });

                Log::error('Refund initiation failed or uncertain', [
                    'refund_id' => $refundId,
                    'error' => $e->getMessage(),
                ]);

                $this->error(
                    "Refund {$refundId} requires review."
                );
            }
        }

        return self::SUCCESS;
    }
}
