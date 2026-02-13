<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\GlobalSettings\Models\Setting;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;
use Spatie\Permission\Models\Role;

final class TenantSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);

        // Create users with roles
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    // ========================================
    // AUTHORIZATION TESTS
    // ========================================

    public function test_guests_cannot_access_tenant_settings(): void
    {
        $response = $this->get(route('admin.organizations.settings'));

        $response->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_access_tenant_settings(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.organizations.settings'));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_tenant_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.organizations.settings'));

        $response->assertOk();
    }

    public function test_super_admin_can_access_tenant_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.organizations.settings'));

        $response->assertOk();
    }

    // ========================================
    // INDEX PAGE TESTS
    // ========================================

    public function test_settings_page_displays_current_config_values(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.organizations.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/Organizations/Settings')
            ->has('settings')
            ->where('settings.entity_name', config('tenants.entity_name'))
            ->where('settings.multi_org', config('tenants.multi_org'))
            ->where('settings.routing_mode', config('tenants.routing_mode'))
            ->where('settings.invitation_expiry_hours', config('tenants.invitation_expiry_hours'))
            ->where('settings.default_member_role', config('tenants.default_member_role'))
        );
    }

    public function test_settings_page_shows_overridden_values_from_database(): void
    {
        Setting::set('tenant.entity_name', 'Team');
        Setting::set('tenant.invitation_expiry_hours', '48');

        $response = $this->actingAs($this->admin)->get(route('admin.organizations.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('settings.entity_name', 'Team')
            ->where('settings.invitation_expiry_hours', 48)
        );
    }

    // ========================================
    // UPDATE TESTS
    // ========================================

    public function test_guests_cannot_update_tenant_settings(): void
    {
        $response = $this->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Team',
            'entity_name_plural' => 'Teams',
            'multi_org' => true,
            'personal_org' => false,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 48,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_update_tenant_settings(): void
    {
        $response = $this->actingAs($this->user)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Team',
            'entity_name_plural' => 'Teams',
            'multi_org' => true,
            'personal_org' => false,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 48,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_tenant_settings(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Team',
            'entity_name_plural' => 'Teams',
            'multi_org' => false,
            'personal_org' => true,
            'routing_mode' => 'url',
            'url_prefix' => 'team',
            'invitation_expiry_hours' => 48,
            'max_organizations_per_user' => 5,
            'max_members_per_organization' => 100,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertRedirect(route('admin.organizations.settings'));
        $response->assertSessionHas('status', 'Tenant settings updated successfully.');

        $this->assertDatabaseHas('settings', ['key' => 'tenant.entity_name', 'value' => 'Team']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.entity_name_plural', 'value' => 'Teams']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.multi_org', 'value' => '0']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.personal_org', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.routing_mode', 'value' => 'url']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.url_prefix', 'value' => 'team']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.invitation_expiry_hours', 'value' => '48']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.max_organizations_per_user', 'value' => '5']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.max_members_per_organization', 'value' => '100']);
        $this->assertDatabaseHas('settings', ['key' => 'tenant.default_member_role', 'value' => 'member']);
    }

    public function test_updated_settings_are_reflected_on_settings_page(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Workspace',
            'entity_name_plural' => 'Workspaces',
            'multi_org' => true,
            'personal_org' => false,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 24,
            'max_organizations_per_user' => 3,
            'max_members_per_organization' => 50,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'member'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.organizations.settings'));

        $response->assertInertia(fn ($page) => $page
            ->where('settings.entity_name', 'Workspace')
            ->where('settings.entity_name_plural', 'Workspaces')
            ->where('settings.personal_org', false)
            ->where('settings.invitation_expiry_hours', 24)
            ->where('settings.max_organizations_per_user', 3)
        );
    }

    // ========================================
    // VALIDATION TESTS
    // ========================================

    public function test_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), []);

        $response->assertSessionHasErrors([
            'entity_name',
            'entity_name_plural',
            'multi_org',
            'personal_org',
            'routing_mode',
            'invitation_expiry_hours',
            'max_organizations_per_user',
            'max_members_per_organization',
            'default_member_role',
            'member_roles',
        ]);
    }

    public function test_validates_routing_mode_enum(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Organization',
            'entity_name_plural' => 'Organizations',
            'multi_org' => true,
            'personal_org' => true,
            'routing_mode' => 'invalid',
            'invitation_expiry_hours' => 72,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertSessionHasErrors('routing_mode');
    }

    public function test_validates_invitation_expiry_range(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Organization',
            'entity_name_plural' => 'Organizations',
            'multi_org' => true,
            'personal_org' => true,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 0,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertSessionHasErrors('invitation_expiry_hours');
    }

    public function test_validates_numeric_limits_not_negative(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Organization',
            'entity_name_plural' => 'Organizations',
            'multi_org' => true,
            'personal_org' => true,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 72,
            'max_organizations_per_user' => -1,
            'max_members_per_organization' => -5,
            'default_member_role' => 'member',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertSessionHasErrors(['max_organizations_per_user', 'max_members_per_organization']);
    }

    public function test_validates_default_member_role_must_be_in_member_roles(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Organization',
            'entity_name_plural' => 'Organizations',
            'multi_org' => true,
            'personal_org' => true,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 72,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'nonexistent',
            'member_roles' => ['owner', 'admin', 'member'],
        ]);

        $response->assertSessionHasErrors('default_member_role');
    }

    public function test_validates_member_roles_not_empty(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.organizations.settings.update'), [
            'entity_name' => 'Organization',
            'entity_name_plural' => 'Organizations',
            'multi_org' => true,
            'personal_org' => true,
            'routing_mode' => 'session',
            'invitation_expiry_hours' => 72,
            'max_organizations_per_user' => 0,
            'max_members_per_organization' => 0,
            'default_member_role' => 'member',
            'member_roles' => [],
        ]);

        $response->assertSessionHasErrors('member_roles');
    }
}
