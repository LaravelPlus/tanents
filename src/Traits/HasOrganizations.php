<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaravelPlus\Tenants\Models\Organization;
use RuntimeException;

trait HasOrganizations
{
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function personalOrganization(): ?Organization
    {
        return $this->ownedOrganizations()
            ->where('is_personal', true)
            ->first();
    }

    public function currentOrganization(): ?Organization
    {
        $organizationId = session('current_organization_id');

        if ($organizationId) {
            return $this->organizations()->where('organizations.id', $organizationId)->first();
        }

        return $this->personalOrganization() ?? $this->organizations()->first();
    }

    public function switchOrganization(Organization $organization): void
    {
        if (!$this->belongsToOrganization($organization)) {
            throw new RuntimeException('User is not a member of this organization.');
        }

        session()->put('current_organization_id', $organization->id);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($organization->id);
        }
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function organizationRole(Organization $organization): ?string
    {
        $member = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        return $member?->pivot->role;
    }

    public function isOrganizationOwner(Organization $organization): bool
    {
        return $this->organizationRole($organization) === 'owner';
    }

    public function isOrganizationAdmin(Organization $organization): bool
    {
        return in_array($this->organizationRole($organization), ['owner', 'admin'], true);
    }

    protected static function bootHasOrganizations(): void
    {
        if (config('tenants.personal_org', true)) {
            static::created(function ($user): void {
                app(\LaravelPlus\Tenants\Services\OrganizationService::class)
                    ->createPersonalOrganization($user->id, $user->name ?? $user->email);
            });
        }
    }
}
