<?php

namespace App\Support;

use App\Services\Checkout\CheckoutService;

/** Reads a "phone or email" field: a Turkish mobile number or an email address. */
class Contact
{
    /** @return array{0: 'phone'|'email', 1: string}|null */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return ['email', mb_strtolower($input)];
        }

        if (preg_match('/^[\d\s()+-]+$/', $input)) {
            $phone = CheckoutService::normalizePhone($input);

            return preg_match('/^\+905\d{9}$/', $phone) ? ['phone', $phone] : null;
        }

        return null;
    }
}
