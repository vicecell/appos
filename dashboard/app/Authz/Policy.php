<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

/**
 * The single place that maps roles and grants to permissions. Default is deny.
 */
final class Policy
{
    /**
     * @return list<Permission>
     */
    private static function permissionsFor(Role $role): array
    {
        return match ($role) {
            Role::ClientAdmin => Permission::cases(),
            Role::Operator => [Permission::ServersRead, Permission::ServersWrite],
            Role::Viewer => [Permission::ServersRead],
        };
    }

    public static function roleAllows(Role $role, Permission $permission): bool
    {
        return in_array($permission, self::permissionsFor($role), true);
    }

    public static function grantAllows(GrantLevel $level, Permission $permission): bool
    {
        return match ($level) {
            GrantLevel::Read => $permission->isRead(),
            GrantLevel::Manage => true,
        };
    }
}
