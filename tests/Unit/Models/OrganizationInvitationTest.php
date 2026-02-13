<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Models;

use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;

final class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_can_be_created_with_factory(): void
    {
        $invitation = OrganizationInvitation::factory()->create();

        $this->assertDatabaseHas('organization_invitations', ['id' => $invitation->id]);
        $this->assertNotNull($invitation->uuid);
        $this->assertNotNull($invitation->token);
    }

    public function test_uuid_and_token_are_auto_generated(): void
    {
        $org = Organization::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->assertNotEmpty($invitation->uuid);
        $this->assertNotEmpty($invitation->token);
        $this->assertEquals(40, mb_strlen($invitation->token));
    }

    public function test_organization_relationship(): void
    {
        $org = Organization::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->assertEquals($org->id, $invitation->organization->id);
    }

    public function test_invited_by_relationship(): void
    {
        $user = User::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'invited_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $invitation->invitedBy->id);
    }

    public function test_is_expired_returns_true_when_expired(): void
    {
        $invitation = OrganizationInvitation::factory()->expired()->create();

        $this->assertTrue($invitation->isExpired());
    }

    public function test_is_expired_returns_false_when_not_expired(): void
    {
        $invitation = OrganizationInvitation::factory()->create([
            'expires_at' => now()->addHours(72),
        ]);

        $this->assertFalse($invitation->isExpired());
    }

    public function test_is_pending_returns_true_for_fresh_invitation(): void
    {
        $invitation = OrganizationInvitation::factory()->create([
            'accepted_at' => null,
            'declined_at' => null,
            'expires_at' => now()->addHours(72),
        ]);

        $this->assertTrue($invitation->isPending());
    }

    public function test_is_pending_returns_false_when_accepted(): void
    {
        $invitation = OrganizationInvitation::factory()->accepted()->create();

        $this->assertFalse($invitation->isPending());
    }

    public function test_is_pending_returns_false_when_declined(): void
    {
        $invitation = OrganizationInvitation::factory()->declined()->create();

        $this->assertFalse($invitation->isPending());
    }

    public function test_is_pending_returns_false_when_expired(): void
    {
        $invitation = OrganizationInvitation::factory()->expired()->create();

        $this->assertFalse($invitation->isPending());
    }

    public function test_accept_sets_accepted_at(): void
    {
        $invitation = OrganizationInvitation::factory()->create();

        $this->assertNull($invitation->accepted_at);

        $invitation->accept();
        $invitation->refresh();

        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_decline_sets_declined_at(): void
    {
        $invitation = OrganizationInvitation::factory()->create();

        $this->assertNull($invitation->declined_at);

        $invitation->decline();
        $invitation->refresh();

        $this->assertNotNull($invitation->declined_at);
    }

    public function test_scope_pending_filters_correctly(): void
    {
        OrganizationInvitation::factory()->create([
            'accepted_at' => null,
            'declined_at' => null,
            'expires_at' => now()->addHours(72),
        ]);
        OrganizationInvitation::factory()->accepted()->create();
        OrganizationInvitation::factory()->declined()->create();
        OrganizationInvitation::factory()->expired()->create();

        $pending = OrganizationInvitation::pending()->get();

        $this->assertCount(1, $pending);
    }

    public function test_scope_expired_filters_correctly(): void
    {
        OrganizationInvitation::factory()->create([
            'expires_at' => now()->addHours(72),
        ]);
        OrganizationInvitation::factory()->expired()->create();

        $expired = OrganizationInvitation::expired()->get();

        $this->assertCount(1, $expired);
    }

    public function test_datetime_casts(): void
    {
        $invitation = OrganizationInvitation::factory()->create([
            'expires_at' => now()->addHours(72),
        ]);

        $this->assertInstanceOf(DateTimeInterface::class, $invitation->expires_at);
    }
}
