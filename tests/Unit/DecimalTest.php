<?php
namespace Tests\Unit;
use App\Services\Decimal as D;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function test_fixed_point_arithmetic_and_rounding(): void
    {
        $this->assertSame('0.30',D::add('0.10','0.20'));
        $this->assertSame('10.01',D::value('10.005'));
        $this->assertSame('-10.01',D::value('-10.005'));
        $this->assertSame('35.75',D::mul('5.50','6.50'));
        $this->assertSame('3.33',D::ratio('10.00','1.00','3.00'));
    }
}
