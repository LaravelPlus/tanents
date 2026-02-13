<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use LaravelPlus\Tenants\Events\InvitationAccepted;
use LaravelPlus\Tenants\Events\InvitationDeclined;
use LaravelPlus\Tenants\Events\InvitationSent;
use LaravelPlus\Tenants\Events\MemberAdded;
use LaravelPlus\Tenants\Mail\OrganizationInvitationMail;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Notifications\InvitationReceivedNotification;
use LaravelPlus\Tenants\Services\InvitationService;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;
use RuntimeException;

final class InvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvitationService();
    }

    public function test_send_creates_invitation(): void
    {
        Mail::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = $this->service->send($org, 'newuser@example.com', 'member', $inviter->id);

        $this->assertInstanceOf(OrganizationInvitation::class, $invitation);
        $this->assertEquals('newuser@example.com', $invitation->email);
        $this->assertEquals('member', $invitation->role);
        $this->assertEquals($inviter->id, $invitation->invited_by);
        $this->assertNotNull($invitation->expires_at);
        $this->assertDatabaseHas('organization_invitations', [
            'email' => 'newuser@example.com',
            'organization_id' => $org->id,
        ]);
    }

    public function test_send_sends_email(): void
    {
        Mail::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $this->service->send($org, 'test@example.com', 'member', $inviter->id);

        Mail::assertSent(OrganizationInvitationMail::class, fn ($mail) => $mail->hasTo('test@example.com'));
    }

    public function test_send_notifies_existing_user(): void
    {
        Mail::fake();
        Notification::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $this->service->send($org, 'existing@example.com', 'member', $inviter->id);

        Notification::assertSentTo($existingUser, InvitationReceivedNotification::class);
    }

    public function test_send_does_not_notify_non_existing_user(): void
    {
        Mail::fake();
        Notification::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $this->service->send($org, 'nonexistent@example.com', 'member', $inviter->id);

        Notification::assertNothingSent();
    }

    public function test_send_dispatches_event(): void
    {
        Mail::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $this->service->send($org, 'test@example.com', 'member', $inviter->id);

        Event::assertDispatched(InvitationSent::class);
    }

    public function test_send_throws_when_duplicate_active_invitation_exists(): void
    {
        Mail::fake();
        Event::fake([InvitationSent::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $this->service->send($org, 'test@example.com', 'member', $inviter->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An active invitation already exists for this email.');

        $this->service->send($org, 'test@example.com', 'admin', $inviter->id);
    }

    public function test_send_allows_reinvite_after_decline(): void
    {
        Mail::fake();
        Event::fake([InvitationSent::class, InvitationDeclined::class]);

        $org = Organization::factory()->create();
        $inviter = User::factory()->create();

        $invitation = $this->service->send($org, 'test@example.com', 'member', $inviter->id);
        $invitation->decline();

        // Delete the declined invitation to allow re-inviting (unique constraint on org+email)
        $invitation->delete();

        $newInvitation = $this->service->send($org, 'test@example.com', 'admin', $inviter->id);

        $this->assertNotEquals($invitation->id, $newInvitation->id);
        $this->assertEquals('admin', $newInvitation->role);
    }

    public function test_accept_invitation(): void
    {
        Event::fake([InvitationAccepted::class, MemberAdded::class]);

        $org = Organization::factory()->create();
        $user = User::factory()->create(['email' => 'invited@example.com']);
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $org->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'expires_at' => now()->addHours(72),
        ]);

        $this->service->accept($invitation);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertTrue($org->hasMember($user));
        $this->assertEquals('member', $org->getMemberRole($user));

        Event::assertDispatched(InvitationAccepted::class);
        Event::assertDispatched(MemberAdded::class);
    }

    public function test_accept_expired_invitation_throws(): void
    {
        $invitation = OrganizationInvitation::factory()->expired()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This invitation has expired.');

        $this->service->accept($invitation);
    }

    public function test_accept_already_accepted_invitation_throws(): void
    {
        $invitation = OrganizationInvitation::factory()->accepted()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This invitation is no longer pending.');

        $this->service->accept($invitation);
    }

    public function test_decline_invitation(): void
    {
        Event::fake([InvitationDeclined::class]);

        $invitation = OrganizationInvitation::factory()->create([
            'expires_at' => now()->addHours(72),
        ]);

        $this->service->decline($invitation);

        $invitation->refresh();
        $this->assertNotNull($invitation->declined_at);

        Event::assertDispatched(InvitationDeclined::class);
    }

    public function test_decline_already_accepted_invitation_throws(): void
    {
        $invitation = OrganizationInvitation::factory()->accepted()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This invitation is no longer pending.');

        $this->service->decline($invitation);
    }

    public function test_cancel_invitation(): void
    {
        $invitation = OrganizationInvitation::factory()->create();

        $result = $this->service->cancel($invitation);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('organization_invitations', ['id' => $invitation->id]);
    }
}
