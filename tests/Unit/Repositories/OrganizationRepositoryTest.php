<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Unit\Repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Repositories\OrganizationRepository;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;

final class OrganizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OrganizationRepository();
    }

    public function test_model_class_is_set(): void
    {
        $this->assertEquals(Organization::class, $this->repository->modelClass);
    }

    public function test_create_organization(): void
    {
        $org = $this->repository->create([
            'name' => 'Test Org',
            'slug' => 'test-org',
        ]);

        $this->assertInstanceOf(Organization::class, $org);
        $this->assertEquals('Test Org', $org->name);
        $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
    }

    public function test_find_returns_organization(): void
    {
        $org = Organization::factory()->create();

        $found = $this->repository->find($org->id);

        $this->assertNotNull($found);
        $this->assertEquals($org->id, $found->id);
    }

    public function test_find_returns_null_for_missing(): void
    {
        $found = $this->repository->find(999);

        $this->assertNull($found);
    }

    public function test_find_or_fail_throws_for_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOrFail(999);
    }

    public function test_find_by_slug(): void
    {
        $org = Organization::factory()->create(['slug' => 'test-slug']);

        $found = $this->repository->findBySlug('test-slug');

        $this->assertNotNull($found);
        $this->assertEquals($org->id, $found->id);
    }

    public function test_find_by_slug_returns_null_for_missing(): void
    {
        $found = $this->repository->findBySlug('nonexistent');

        $this->assertNull($found);
    }

    public function test_find_by_uuid(): void
    {
        $org = Organization::factory()->create();

        $found = $this->repository->findByUuid($org->uuid);

        $this->assertNotNull($found);
        $this->assertEquals($org->id, $found->id);
    }

    public function test_update_organization(): void
    {
        $org = Organization::factory()->create(['name' => 'Old Name']);

        $updated = $this->repository->update($org, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('organizations', ['name' => 'New Name']);
    }

    public function test_delete_organization(): void
    {
        $org = Organization::factory()->create();

        $result = $this->repository->delete($org);

        $this->assertTrue($result);
        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
    }

    public function test_all_returns_collection(): void
    {
        Organization::factory()->count(3)->create();

        $all = $this->repository->all();

        $this->assertCount(3, $all);
    }

    public function test_paginate_returns_paginator(): void
    {
        Organization::factory()->count(20)->create();

        $result = $this->repository->paginate(10);

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
    }

    public function test_for_user_returns_user_organizations(): void
    {
        $user = User::factory()->create();
        // User now has an auto-created personal org from HasOrganizations trait
        $personalOrgCount = $user->organizations()->count();

        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $orgOther = Organization::factory()->create();

        $org1->addMember($user, 'owner');
        $org2->addMember($user, 'member');

        $result = $this->repository->forUser($user->id);

        $this->assertCount($personalOrgCount + 2, $result);
        $this->assertTrue($result->contains('id', $org1->id));
        $this->assertTrue($result->contains('id', $org2->id));
        $this->assertFalse($result->contains('id', $orgOther->id));
    }

    public function test_personal_for_user_returns_personal_org(): void
    {
        $user = User::factory()->create();
        // User now has an auto-created personal org from HasOrganizations trait
        $autoPersonal = $user->personalOrganization();
        Organization::factory()->withOwner($user->id)->create(['is_personal' => false]);

        $result = $this->repository->personalForUser($user->id);

        $this->assertNotNull($result);
        $this->assertNotNull($autoPersonal);
        $this->assertEquals($autoPersonal->id, $result->id);
        $this->assertTrue($result->is_personal);
    }

    public function test_personal_for_user_returns_auto_created_org(): void
    {
        $user = User::factory()->create();
        // HasOrganizations trait auto-creates a personal org

        $result = $this->repository->personalForUser($user->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_personal);
        $this->assertEquals($user->id, $result->owner_id);
    }
}
