<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use Razorpay\Api\Api;

class RazorpayService implements PaymentGatewayInterface
{
    private Api $api;

    public function __construct()
    {
        $this->api = new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));
    }

    public function createPayment(int $amountInPaise, string $receipt): array
    {
        $order = $this->api->order->create([
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'receipt' => $receipt
        ]);

        return $order->toArray();
    }

    public function fetchOrderPayments(string $gatewayOrderId){
        return $this->api->order
        ->fetch($gatewayOrderId)
        ->payments()
        ->toArray();
    }

    public function fetchOrder(string $gatewayOrderId): array
    {
        return $this->api->order
            ->fetch($gatewayOrderId)
            ->toArray();
    }

    public function refund(
        string $gatewayPaymentId,
        int $amountInPaise,
        string $receipt
        ): array{
        return $this->api->payment->fetch($gatewayPaymentId)
        ->refund([
            'amount' => $amountInPaise,
            'speed' => 'normal',
            'notes' => [
                'receipt' => $receipt,
                'reason' => 'order_expired'
            ],
        ])->toArray();
    }

    public function fetchPaymentRefunds(string $gatewayPaymentId) : array {
        return $this->api->payment->fetch($gatewayPaymentId)->refunds()->toArray();
    }
}
