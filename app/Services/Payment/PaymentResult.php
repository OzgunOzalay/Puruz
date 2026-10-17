<?php

namespace App\Services\Payment;

final readonly class PaymentResult
{
    /**
     * @param  array<string, string>  $transactionIds  basket item id => iyzico paymentTransactionId
     */
    public function __construct(
        public bool $successful,
        public bool $signatureValid,
        public ?string $paymentId,
        public ?string $conversationId,
        public ?string $basketId,
        public ?int $paidPrice,
        public ?int $price,
        public ?string $currency,
        public ?int $installment = null,
        public ?string $cardFamily = null,
        public ?string $cardAssociation = null,
        public ?string $lastFour = null,
        public array $transactionIds = [],
        public ?string $errorMessage = null,
        public array $raw = [],
    ) {}
}
