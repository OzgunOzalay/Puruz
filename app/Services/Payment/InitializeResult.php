<?php

namespace App\Services\Payment;

final readonly class InitializeResult
{
    public function __construct(
        public string $token,
        public string $paymentPageUrl,
    ) {}
}
