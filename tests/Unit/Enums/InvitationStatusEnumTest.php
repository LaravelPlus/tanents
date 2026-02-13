<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Enums;

use LaravelPlus\Tenants\Enums\InvitationStatus;
use LaravelPlus\Tenants\Tests\TestCase;

final class InvitationStatusEnumTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertEquals('pending', InvitationStatus::Pending->value);
        $this->assertEquals('accepted', InvitationStatus::Accepted->value);
        $this->assertEquals('declined', InvitationStatus::Declined->value);
        $this->assertEquals('expired', InvitationStatus::Expired->value);
    }

    public function test_cases_count(): void
    {
        $this->assertCount(4, InvitationStatus::cases());
    }

    public function test_from_string(): void
    {
        $this->assertEquals(InvitationStatus::Pending, InvitationStatus::from('pending'));
        $this->assertEquals(InvitationStatus::Accepted, InvitationStatus::from('accepted'));
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $this->assertNull(InvitationStatus::tryFrom('invalid'));
    }
}
