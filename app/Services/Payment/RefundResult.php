<?php

namespace App\Services\Payment;

final readonly class RefundResult
{
    public function __construct(
        public bool $successful,
        public ?string $reference = null,
        public ?string $errorMessage = null,
    ) {}
}
