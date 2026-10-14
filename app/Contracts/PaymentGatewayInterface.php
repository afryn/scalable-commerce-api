<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function createPayment(int $amountInPaise, string $reciept): array;
}
