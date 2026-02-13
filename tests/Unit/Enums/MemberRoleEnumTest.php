<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Enums;

use LaravelPlus\Tenants\Enums\MemberRole;
use LaravelPlus\Tenants\Tests\TestCase;

final class MemberRoleEnumTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertEquals('owner', MemberRole::Owner->value);
        $this->assertEquals('admin', MemberRole::Admin->value);
        $this->assertEquals('member', MemberRole::Member->value);
    }

    public function test_label_returns_ucfirst(): void
    {
        $this->assertEquals('Owner', MemberRole::Owner->label());
        $this->assertEquals('Admin', MemberRole::Admin->label());
        $this->assertEquals('Member', MemberRole::Member->label());
    }

    public function test_from_config_returns_configured_roles(): void
    {
        config(['tenants.member_roles' => ['owner', 'admin', 'member']]);

        $roles = MemberRole::fromConfig();

        $this->assertCount(3, $roles);
        $this->assertContains(MemberRole::Owner, $roles);
        $this->assertContains(MemberRole::Admin, $roles);
        $this->assertContains(MemberRole::Member, $roles);
    }

    public function test_from_config_skips_invalid_roles(): void
    {
        config(['tenants.member_roles' => ['owner', 'nonexistent', 'member']]);

        $roles = MemberRole::fromConfig();

        $this->assertCount(2, $roles);
        $this->assertContains(MemberRole::Owner, $roles);
        $this->assertContains(MemberRole::Member, $roles);
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $this->assertNull(MemberRole::tryFrom('invalid'));
    }

    public function test_from_string(): void
    {
        $role = MemberRole::from('owner');
        $this->assertEquals(MemberRole::Owner, $role);
    }
}
