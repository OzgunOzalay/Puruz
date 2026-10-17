<?php

namespace Tests;

use App\Services\Payment\FakeGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        FakeGateway::$results = [];
        FakeGateway::$failNextRefund = false;
    }

    /** Valid checkout form input; override fields as needed. */
    protected function checkoutData(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.com',
            'customer_phone' => '0532 123 45 67',
            'shipping_city' => 'İzmir',
            'shipping_district' => 'Karşıyaka',
            'shipping_address' => 'Bostanlı Mah. 1234 Sok. No: 5',
            'billing_same' => '1',
            'billing_type' => 'individual',
            'accept_terms' => '1',
            'accept_kvkk' => '1',
        ], $overrides);
    }
}
