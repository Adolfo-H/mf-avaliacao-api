<?php

namespace Tests\Unit;

use App\Support\AdminPasswordPolicy;
use PHPUnit\Framework\TestCase;

final class AdminPasswordPolicyTest extends TestCase
{
    public function test_accepts_long_password_with_letters_and_numbers(): void
    {
        $this->assertTrue(AdminPasswordPolicy::isValid('ExemploSeguro987!'));
    }

    public function test_rejects_short_password(): void
    {
        $this->assertFalse(AdminPasswordPolicy::isValid('teste123'));
    }

    public function test_rejects_password_without_number_or_letter(): void
    {
        $this->assertFalse(AdminPasswordPolicy::isValid('ApenasLetrasLongas'));
        $this->assertFalse(AdminPasswordPolicy::isValid('1234567891234567'));
    }

    public function test_rejects_overlong_password(): void
    {
        $this->assertFalse(AdminPasswordPolicy::isValid(str_repeat('A1', 110)));
    }
}
