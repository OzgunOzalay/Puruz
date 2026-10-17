<?php

namespace Tests\Unit;

use App\Services\Payment\BasketBuilder;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_turkish_lira(): void
    {
        $this->assertSame('1.250 ₺', Money::format(125000));
        $this->assertSame('1.250,50 ₺', Money::format(125050));
        $this->assertSame('0 ₺', Money::format(null));
    }

    public function test_converts_to_iyzico_decimal(): void
    {
        $this->assertSame('1250', Money::toDecimal(125000));
        $this->assertSame('1250.5', Money::toDecimal(125050));
        $this->assertSame('0.99', Money::toDecimal(99));
    }

    public function test_parses_admin_input(): void
    {
        $this->assertSame(125050, Money::parse('1.250,50'));
        $this->assertSame(65000, Money::parse('650'));
        $this->assertSame(64990, Money::parse('649.90'));
        $this->assertSame(200000, Money::parse('2.000'));
        $this->assertSame(125000050, Money::parse('1.250.000,50'));
        $this->assertSame(250, Money::parse('2.5'));
        $this->assertSame(120, Money::parse(1.2));
        $this->assertNull(Money::parse(''));
    }

    #[DataProvider('discounts')]
    public function test_discount_distribution_always_adds_up(array $lines, int $discount): void
    {
        $result = BasketBuilder::distributeDiscount($lines, $discount);

        $this->assertSame(array_sum($lines) - min($discount, array_sum($lines)), array_sum($result));
        $this->assertSame(array_keys($lines), array_keys($result));
        foreach ($result as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    public static function discounts(): array
    {
        return [
            'no discount' => [['a' => 1000, 'b' => 2000], 0],
            'uneven split' => [['a' => 333, 'b' => 333, 'c' => 334], 100],
            'ten percent' => [['a' => 65000, 'b' => 320000], 38500],
            'rounding remainder' => [['a' => 1, 'b' => 1, 'c' => 1], 2],
            'whole basket' => [['a' => 500, 'b' => 700], 1200],
            'more than basket' => [['a' => 500], 900],
        ];
    }
}
