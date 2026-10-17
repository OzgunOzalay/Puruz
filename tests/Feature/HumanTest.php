<?php

namespace Tests\Feature;

use App\Filament\Support\PageSummary;
use App\Support\Human;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class HumanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
    }

    public function test_durations_read_naturally(): void
    {
        $this->assertSame('bugünden beri', Human::since(now()->setTime(9, 0)));
        $this->assertSame('12 gündür', Human::since(now()->subDays(12)));
        $this->assertSame('aynı gün', Human::took(now()->setTime(9, 0), now()));
        $this->assertSame('18 günde', Human::took(now()->subDays(18), now()));
        $this->assertSame(0, Human::days(now()->addDays(3))); // future dates never go negative
    }

    public function test_dates_read_naturally(): void
    {
        $this->assertSame('bugün', Human::day(now()));
        $this->assertSame('dün', Human::day(now()->subDay()));
        $this->assertSame('3 Kas', Human::day(CarbonImmutable::parse('2026-11-03')));
        $this->assertSame('3 Kas 2025', Human::day(CarbonImmutable::parse('2025-11-03')));
        $this->assertSame('bugün 14:20', Human::date(now()->setTime(14, 20)));
        $this->assertSame('dün 09:05', Human::date(now()->subDay()->setTime(9, 5)));
    }

    public function test_turkish_suffix_after_numbers(): void
    {
        $this->assertSame('1’i', Human::ofThem(1));
        $this->assertSame('2’si', Human::ofThem(2));
        $this->assertSame('3’ü', Human::ofThem(3));
        $this->assertSame('6’sı', Human::ofThem(6));
        $this->assertSame('9’u', Human::ofThem(9));
        $this->assertSame('10’u', Human::ofThem(10));
        $this->assertSame('20’si', Human::ofThem(20));
        $this->assertSame('40’ı', Human::ofThem(40));
    }

    public function test_page_summary_joins_parts_and_skips_empty_ones(): void
    {
        $html = (string) PageSummary::make([['14 parça satışta', '(8.450 ₺ değerinde)'], [null, 'yok'], ['2 taslak']]);

        $this->assertStringContainsString('<strong', $html);
        $this->assertStringContainsString('14 parça satışta</strong> (8.450 ₺ değerinde)', $html);
        $this->assertStringContainsString('2 taslak</strong>', $html);
        $this->assertStringNotContainsString('yok', $html);
        $this->assertSame(1, substr_count($html, '·'));
    }
}
