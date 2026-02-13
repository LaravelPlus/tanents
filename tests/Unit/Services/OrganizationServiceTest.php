<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LaravelPlus\Tenants\Events\MemberAdded;
use LaravelPlus\Tenants\Events\MemberRemoved;
use LaravelPlus\Tenants\Events\MemberRoleChanged;
use LaravelPlus\Tenants\Events\OrganizationCreated;
use LaravelPlus\Tenants\Events\OrganizationDeleted;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Services\OrganizationService;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;
use RuntimeException;

final class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationService::class);
    }

    public function test_list_returns_paginated_organizations(): void
    {
        Organization::factory()->count(5)->create();

        $result = $this->service->list(10);

        $this->assertCount(5, $result->items());
    }

    public function test_list_with_search_filters_results(): void
    {
        Organization::factory()->create(['name' => 'Acme Corp']);
        Organization::factory()->create(['name' => 'Beta Inc']);
        Organization::factory()->create(['name' => 'Gamma LLC']);

        $result = $this->service->list(10, 'Acme');

        $this->assertCount(1, $result->items());
        $this->assertEquals('Acme Corp', $result->items()[0]->name);
    }

    public function test_list_search_matches_slug(): void
    {
        Organization::factory()->create(['name' => 'My Org', 'slug' => 'my-org']);
        Organization::factory()->create(['name' => 'Other', 'slug' => 'other']);

        $result = $this->service->list(10, 'my-org');

        $this->assertCount(1, $result->items());
    }

    public function test_list_search_matches_description(): void
    {
        Organization::factory()->create(['description' => 'A software company']);
        Organization::factory()->create(['description' => 'A hardware store']);

        $result = $this->service->list(10, 'software');

        $this->assertCount(1, $result->items());
    }

    public function test_create_organization(): void
    {
        Event::fake([OrganizationCreated::class]);

        $user = User::factory()->create();

        $org = $this->service->create(['name' => 'New Org'], $user->id);

        $this->assertEquals('New Org', $org->name);
        $this->assertEquals('new-org', $org->slug);
        $this->assertEquals($user->id, $org->owner_id);
        $this->assertTrue($org->hasMember($user));
        $this->assertEquals('owner', $org->getMemberRole($user));

        Event::assertDispatched(OrganizationCreated::class);
    }

    public function test_create_organization_without_owner(): void
    {
        Event::fake([OrganizationCreated::class]);

        $org = $this->service->create(['name' => 'Ownerless Org']);

        $this->assertEquals('Ownerless Org', $org->name);
        $this->assertNull($org->owner_id);
        $this->assertCount(0, $org->members);

        Event::assertDispatched(OrganizationCreated::class);
    }

    public function test_create_uses_provided_slug(): void
    {
        Event::fake([OrganizationCreated::class]);

        $org = $this->service->create([
            'name' => 'My Org',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $org->slug);
    }

    public function test_update_organization(): void
    {
        $org = Organization::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->update($org, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new-name', $updated->slug);
    }

    public function test_update_preserves_slug_when_explicitly_set(): void
    {
        $org = Organization::factory()->create();

        $updated = $this->service->update($org, [
            'name' => 'Updated Name',
            'slug' => 'keep-this-slug',
        ]);

        $this->assertEquals('keep-this-slug', $updated->slug);
    }

    public function test_delete_organization(): void
    {
        Event::fake([OrganizationDeleted::class]);

        $org = Organization::factory()->create();

        $result = $this->service->delete($org);

        $this->assertTrue($result);
        $this->assertSoftDeleted('organizations', ['id' => $org->id]);

        Event::assertDispatched(OrganizationDeleted::class);
    }

    public function test_create_personal_organization(): void
    {
        Event::fake([OrganizationCreated::class]);

        $user = User::factory()->create();

        $org = $this->service->createPersonalOrganization($user->id, 'John Doe');

        $this->assertEquals("John Doe's Organization", $org->name);
        $this->assertTrue($org->is_personal);
        $this->assertEquals($user->id, $org->owner_id);
        $this->assertTrue($org->hasMember($user));
    }

    public function test_add_member(): void
    {
        Event::fake([MemberAdded::class]);

        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->service->addMember($org, $user->id, 'admin');

        $this->assertTrue($org->hasMember($user));
        $this->assertEquals('admin', $org->getMemberRole($user));

        Event::assertDispatched(MemberAdded::class, fn ($event) => $event->organization->id === $org->id
                && $event->user->id === $user->id
                && $event->role === 'admin');
    }

    public function test_add_member_respects_limit(): void
    {
        config(['tenants.max_members_per_organization' => 2]);

        $org = Organization::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $org->addMember($user1, 'owner');
        $org->addMember($user2, 'member');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization has reached its member limit.');

        $this->service->addMember($org, $user3->id, 'member');
    }

    public function test_remove_member(): void
    {
        Event::fake([MemberRemoved::class]);

        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'member');

        $this->service->removeMember($org, $user->id);

        $this->assertFalse($org->hasMember($user));

        Event::assertDispatched(MemberRemoved::class, fn ($event) => $event->organization->id === $org->id
                && $event->user->id === $user->id);
    }

    public function test_change_member_role(): void
    {
        Event::fake([MemberRoleChanged::class]);

        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'member');

        $this->service->changeMemberRole($org, $user->id, 'admin');

        $this->assertEquals('admin', $org->getMemberRole($user));

        Event::assertDispatched(MemberRoleChanged::class, fn ($event) => $event->organization->id === $org->id
                && $event->user->id === $user->id
                && $event->oldRole === 'member'
                && $event->newRole === 'admin');
    }

    public function test_switch_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'member');

        $this->service->switchOrganization($user->id, $org);

        $this->assertEquals($org->id, session('current_organization_id'));
    }

    public function test_switch_organization_fails_for_non_member(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User is not a member of this organization.');

        $this->service->switchOrganization($user->id, $org);
    }
}
