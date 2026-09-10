<?php

namespace App\Modules\Authorization\Domain;

final class AdminPermissionMatrix
{
    public const NAMES = [
        'create-role', 'update-role', 'delete-role', 'view-roles',
        'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
        'manage-companies', 'view-companies',
        'manage-products', 'view-products',
        'manage-inventory', 'view-inventory',
        'manage-sales', 'view-sales',
        'manage-customers', 'view-customers',
        'view-reports',
        'manage-automations', 'view-automations',
        'manage-users', 'view-users',
        'manage-branches', 'view-branches', 'view-all-branches',
    ];
}
