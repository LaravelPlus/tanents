<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Entity Name
    |--------------------------------------------------------------------------
    */
    'entity_name' => 'Organization',
    'entity_name_plural' => 'Organizations',

    /*
    |--------------------------------------------------------------------------
    | Multi-Organization Mode
    |--------------------------------------------------------------------------
    | When true, users can belong to multiple organizations.
    */
    'multi_org' => false,

    /*
    |--------------------------------------------------------------------------
    | Personal Organization
    |--------------------------------------------------------------------------
    | When true, a personal organization is auto-created on user registration.
    */
    'personal_org' => false,

    /*
    |--------------------------------------------------------------------------
    | Routing Mode
    |--------------------------------------------------------------------------
    | 'session' — current org stored in session
    | 'url'     — current org resolved from URL prefix
    */
    'routing_mode' => 'session',
    'url_prefix' => 'org',

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */
    'invitation_expiry_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    | 0 = unlimited
    */
    'max_organizations_per_user' => 0,
    'max_members_per_organization' => 0,

    /*
    |--------------------------------------------------------------------------
    | Member Roles
    |--------------------------------------------------------------------------
    */
    'default_member_role' => 'member',
    'member_roles' => ['owner', 'admin', 'member'],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'enabled' => false,
        'prefix' => 'admin/organizations',
        'middleware' => ['web', 'auth', 'role:super-admin,admin'],
    ],
];
