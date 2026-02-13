<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;

final class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_created_with_factory(): void
    {
        $org = Organization::factory()->create(['name' => 'Test Org']);

        $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
        $this->assertNotNull($org->uuid);
        $this->assertNotNull($org->slug);
    }

    public function test_uuid_and_slug_are_auto_generated(): void
    {
        $org = Organization::factory()->create([
            'name' => 'My Company',
            'slug' => null,
        ]);

        $this->assertNotEmpty($org->uuid);
        $this->assertEquals('my-company', $org->slug);
    }

    public function test_slug_is_not_overwritten_if_provided(): void
    {
        $org = Organization::factory()->create([
            'name' => 'My Company',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $org->slug);
    }

    public function test_route_key_name_is_slug(): void
    {
        $org = Organization::factory()->create();

        $this->assertEquals('slug', $org->getRouteKeyName());
    }

    public function test_is_personal_returns_correct_value(): void
    {
        $personal = Organization::factory()->personal()->create();
        $team = Organization::factory()->create(['is_personal' => false]);

        $this->assertTrue($personal->isPersonal());
        $this->assertFalse($team->isPersonal());
    }

    public function test_is_owned_by_checks_owner(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->withOwner($user->id)->create();

        $this->assertTrue($org->isOwnedBy($user));

        $otherUser = User::factory()->create();
        $this->assertFalse($org->isOwnedBy($otherUser));
    }

    public function test_owner_relationship(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->withOwner($user->id)->create();

        $this->assertEquals($user->id, $org->owner->id);
    }

    public function test_add_member_and_has_member(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($org->hasMember($user));

        $org->addMember($user, 'admin');

        $this->assertTrue($org->hasMember($user));
    }

    public function test_get_member_role(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $org->addMember($user, 'admin');

        $this->assertEquals('admin', $org->getMemberRole($user));
    }

    public function test_get_member_role_returns_null_for_non_member(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->assertNull($org->getMemberRole($user));
    }

    public function test_remove_member(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'member');

        $this->assertTrue($org->hasMember($user));

        $org->removeMember($user);

        $this->assertFalse($org->hasMember($user));
    }

    public function test_change_member_role(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'member');

        $org->changeMemberRole($user, 'admin');

        $this->assertEquals('admin', $org->getMemberRole($user));
    }

    public function test_members_relationship_returns_users(): void
    {
        $org = Organization::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $org->addMember($user1, 'owner');
        $org->addMember($user2, 'member');

        $this->assertCount(2, $org->members);
        $this->assertTrue($org->members->contains('id', $user1->id));
        $this->assertTrue($org->members->contains('id', $user2->id));
    }

    public function test_members_pivot_has_role_and_joined_at(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->addMember($user, 'admin');

        $member = $org->members()->where('users.id', $user->id)->first();

        $this->assertEquals('admin', $member->pivot->role);
        $this->assertNotNull($member->pivot->joined_at);
    }

    public function test_invitations_relationship(): void
    {
        $org = Organization::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->assertCount(1, $org->invitations);
        $this->assertEquals($invitation->id, $org->invitations->first()->id);
    }

    public function test_soft_delete(): void
    {
        $org = Organization::factory()->create();
        $org->delete();

        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
        $this->assertNull(Organization::find($org->id));
        $this->assertNotNull(Organization::withTrashed()->find($org->id));
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $org = Organization::factory()->create([
            'metadata' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $org->refresh();

        $this->assertIsArray($org->metadata);
        $this->assertEquals('value', $org->metadata['key']);
        $this->assertEquals(1, $org->metadata['nested']['a']);
    }
}
