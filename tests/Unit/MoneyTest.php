<?php

namespace Tests\Unit;

use App\Support\Money;
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
}
