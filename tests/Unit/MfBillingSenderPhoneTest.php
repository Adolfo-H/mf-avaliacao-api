<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MfBillingSenderPhone as Sender;
use PHPUnit\Framework\TestCase;

final class MfBillingSenderPhoneTest extends TestCase
{
    public function test_exact_sender_number_is_accepted(): void
    {
        $this->assertTrue(Sender::matches('5511998765432', '5511998765432'));
        $this->assertTrue(Sender::matches('12025550123', '12025550123'));
    }

    public function test_brazilian_mobile_without_ninth_digit_is_accepted_in_both_directions(): void
    {
        $this->assertTrue(Sender::matches('5511998765432', '551198765432'));
        $this->assertTrue(Sender::matches('551198765432', '5511998765432'));
        $this->assertTrue(Sender::matches('5511999991111', '551199991111'));
    }

    public function test_same_last_digits_do_not_authorize_different_senders(): void
    {
        $this->assertFalse(Sender::matches('5511998765432', '552198765432'));
        $this->assertFalse(Sender::matches('5511998765432', '551198765433'));
        $this->assertFalse(Sender::matches('5511998765432', '552798765432'));
        $this->assertFalse(Sender::matches('5511998765432', '551188765432'));
    }

    public function test_no_heuristic_outside_brazilian_mobile_and_no_empty_numbers(): void
    {
        $this->assertFalse(Sender::matches(null, '551198765432'));
        $this->assertFalse(Sender::matches('', '551198765432'));
        $this->assertFalse(Sender::matches('551132345678', '5511932345678'));
        $this->assertFalse(Sender::matches('12025550123', '12095550123'));
    }
}
