<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => OrderStatus::Paid,
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.com',
            'customer_phone' => '+905321234567',
            'requires_shipping' => true,
            'shipping_name' => 'Ayşe Yılmaz',
            'shipping_city' => 'İzmir',
            'shipping_district' => 'Karşıyaka',
            'shipping_address' => 'Bostanlı Mah. 1234 Sok. No 5',
            'billing_city' => 'İzmir',
            'billing_district' => 'Karşıyaka',
            'billing_address' => 'Bostanlı Mah. 1234 Sok. No 5',
            'subtotal' => 50000,
            'shipping_total' => 9000,
            'total' => 59000,
            'payment_id' => '123456',
            'paid_at' => now(),
        ];
    }
}
