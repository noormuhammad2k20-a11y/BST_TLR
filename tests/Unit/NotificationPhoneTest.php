<?php

namespace Tests\Unit;

use App\Services\NotificationPhone;
use PHPUnit\Framework\TestCase;

final class NotificationPhoneTest extends TestCase
{
    public function test_pakistan_formats_are_normalized_without_accepting_invalid_numbers(): void
    {
        foreach (['03453587094', '3453587094', '923453587094', '+923453587094', '0345-3587094', '+92 345 358 7094'] as $phone) {
            $this->assertSame('923453587094', NotificationPhone::normalize($phone));
            $this->assertSame('+923453587094', NotificationPhone::normalize($phone, true));
        }
        foreach ([null, '', '03xx-xxxxxxx', '123', '00000000000', '+1234567890', 'call03453587094', '034535870940', '++923453587094'] as $phone) {
            $this->assertNull(NotificationPhone::normalize($phone));
        }
    }
}
