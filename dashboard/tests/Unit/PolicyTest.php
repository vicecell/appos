<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ServerWatch\Authz\GrantLevel;
use ServerWatch\Authz\Permission;
use ServerWatch\Authz\Policy;
use ServerWatch\Authz\Role;

final class PolicyTest extends TestCase
{
    public function testRoleMatrix(): void
    {
        self::assertTrue(Policy::roleAllows(Role::Viewer, Permission::ServersRead));
        self::assertFalse(Policy::roleAllows(Role::Viewer, Permission::ServersWrite));
        self::assertFalse(Policy::roleAllows(Role::Viewer, Permission::MembersRead));

        self::assertTrue(Policy::roleAllows(Role::Operator, Permission::ServersRead));
        self::assertTrue(Policy::roleAllows(Role::Operator, Permission::ServersWrite));
        self::assertFalse(Policy::roleAllows(Role::Operator, Permission::MembersRead));

        foreach (Permission::cases() as $p) {
            self::assertTrue(Policy::roleAllows(Role::ClientAdmin, $p), $p->value);
        }
    }

    public function testGrantLevelsAreNarrowerThanOrEqualToTenantAdmin(): void
    {
        self::assertTrue(Policy::grantAllows(GrantLevel::Read, Permission::ServersRead));
        self::assertTrue(Policy::grantAllows(GrantLevel::Read, Permission::MembersRead));
        self::assertFalse(Policy::grantAllows(GrantLevel::Read, Permission::ServersWrite));
        self::assertTrue(Policy::grantAllows(GrantLevel::Manage, Permission::ServersWrite));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownNames(): array
    {
        return [
            'superuser' => ['superuser'],
            'platform_admin is not a tenant role' => ['platform_admin'],
            'wrong case' => ['ADMIN'],
            'empty' => [''],
            'trailing space' => ['viewer '],
            'grant level root' => ['root'],
        ];
    }

    #[DataProvider('unknownNames')]
    public function testUnknownRoleAndGrantStringsFailClosed(string $name): void
    {
        self::assertNull(Role::tryFrom($name));
        self::assertNull(GrantLevel::tryFrom($name));
    }
}
