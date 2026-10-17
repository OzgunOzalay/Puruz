<?php

namespace App\Services\Payment;

use App\Models\Order;

interface PaymentGateway
{
    /** Starts a hosted payment page for the order. */
    public function initialize(Order $order, string $callbackUrl, string $ip): InitializeResult;

    /** Fetches the outcome of a payment by its checkout token. */
    public function retrieve(string $token): PaymentResult;

    /** Voids the whole payment; only possible on the day of the payment. */
    public function cancel(Order $order, string $ip): RefundResult;

    /** Refunds part of a paid basket item, identified by its iyzico transaction id. */
    public function refund(Order $order, string $transactionId, int $amount, string $ip): RefundResult;

    /** Checks the X-IYZ-SIGNATURE-V3 header of a webhook call. */
    public function verifyWebhook(array $payload, ?string $signature): bool;
}
