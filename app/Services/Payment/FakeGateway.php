<?php

namespace App\Services\Payment;

use App\Models\Order;

/**
 * Stand-in gateway for local development and tests. The "payment page" is a local route
 * that lets you choose success or failure.
 */
class FakeGateway implements PaymentGateway
{
    /** Results handed out by retrieve(), keyed by token. Tests can set these directly. */
    public static array $results = [];

    public static bool $failNextRefund = false;

    public function initialize(Order $order, string $callbackUrl, string $ip): InitializeResult
    {
        $token = 'fake-'.$order->id.'-'.bin2hex(random_bytes(6));

        return new InitializeResult($token, route('payment.fake', ['token' => $token]));
    }

    public function retrieve(string $token): PaymentResult
    {
        if (isset(self::$results[$token])) {
            return self::$results[$token];
        }

        $order = Order::query()->where('payment_token', $token)->with('items')->first();
        $succeeded = cache()->pull('fake-payment:'.$token) === 'success';

        if (! $order || ! $succeeded) {
            return new PaymentResult(false, true, null, $order?->number, $order?->number, null, null, 'TRY', errorMessage: 'Kart reddedildi (test).');
        }

        return self::successFor($order);
    }

    public static function successFor(Order $order): PaymentResult
    {
        $order->loadMissing('items');
        $transactionIds = [];
        foreach (BasketBuilder::lines($order) as $index => $line) {
            $transactionIds[$line['id']] = (string) (900000 + $order->id * 100 + $index);
        }

        return new PaymentResult(
            successful: true,
            signatureValid: true,
            paymentId: (string) (800000 + $order->id),
            conversationId: $order->number,
            basketId: $order->number,
            paidPrice: $order->total,
            price: $order->total,
            currency: 'TRY',
            installment: 1,
            cardFamily: 'Bonus',
            cardAssociation: 'MASTER_CARD',
            lastFour: '0008',
            transactionIds: $transactionIds,
        );
    }

    public function cancel(Order $order, string $ip): RefundResult
    {
        return $this->refundOutcome();
    }

    public function refund(Order $order, string $transactionId, int $amount, string $ip): RefundResult
    {
        return $this->refundOutcome();
    }

    private function refundOutcome(): RefundResult
    {
        if (self::$failNextRefund) {
            self::$failNextRefund = false;

            return new RefundResult(false, errorMessage: 'Test iadesi reddedildi.');
        }

        return new RefundResult(true, 'fake-'.bin2hex(random_bytes(4)));
    }

    public function verifyWebhook(array $payload, ?string $signature): bool
    {
        return $signature === 'fake-signature';
    }
}
