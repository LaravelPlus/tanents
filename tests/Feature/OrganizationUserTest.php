<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;

final class OrganizationUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_organizations_index(): void
    {
        $response = $this->get(route('organizations.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_create_organization(): void
    {
        $response = $this->get(route('organizations.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_organizations_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('organizations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->has('organizations')
        );
    }

    public function test_user_can_view_create_organization_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('organizations.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Create')
        );
    }

    public function test_user_can_create_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Test Organization',
            'description' => 'A test organization',
        ]);

        $response->assertRedirect(route('organizations.index'));

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'owner_id' => $user->id,
            'is_personal' => false,
        ]);
    }

    public function test_user_becomes_owner_of_created_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'My Team',
        ]);

        $organization = Organization::where('name', 'My Team')->first();

        $this->assertNotNull($organization);
        $this->assertEquals($user->id, $organization->owner_id);
        $this->assertTrue($user->belongsToOrganization($organization));
    }

    public function test_organization_creation_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_user_can_view_organization_they_belong_to(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->withOwner($user->id)->create();
        $organization->addMember($user, 'owner');

        $response = $this->actingAs($user)->get(route('organizations.show', $organization));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Show')
            ->has('organization')
        );
    }

    public function test_user_cannot_view_organization_they_dont_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->withOwner($otherUser->id)->create();
        $organization->addMember($otherUser, 'owner');

        $response = $this->actingAs($user)->get(route('organizations.show', $organization));

        $response->assertStatus(403);
    }

    public function test_personal_organization_is_auto_created_for_new_users(): void
    {
        $user = User::factory()->create();

        $personalOrg = $user->personalOrganization();

        $this->assertNotNull($personalOrg);
        $this->assertTrue($personalOrg->is_personal);
        $this->assertEquals($user->id, $personalOrg->owner_id);
    }

    public function test_user_organizations_include_personal_org(): void
    {
        $user = User::factory()->create();

        $organizations = $user->organizations;

        $this->assertGreaterThanOrEqual(1, $organizations->count());
        $this->assertTrue($organizations->contains(fn ($org) => $org->is_personal));
    }

    public function test_organization_slug_is_auto_generated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'My Awesome Team',
        ]);

        $organization = Organization::where('name', 'My Awesome Team')->first();

        $this->assertNotNull($organization);
        $this->assertNotEmpty($organization->slug);
    }
}
