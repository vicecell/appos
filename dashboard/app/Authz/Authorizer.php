<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

use ServerWatch\Auth\Principal;
use ServerWatch\Core\Clock;
use ServerWatch\Repositories\GrantRepository;
use ServerWatch\Repositories\MemberRepository;

/**
 * Decides whether a principal may use a permission on one tenant. Order matters and is fail-closed:
 *   1. active membership in an active org -> the role decides
 *   2. platform admin with an explicit unexpired grant -> the grant level decides (flagged for audit)
 *   3. everything else -> NotFound
 * An unknown role/level string from the database yields NotFound, never Allow.
 */
final class Authorizer
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly GrantRepository $grants,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array{outcome: Outcome, viaGrant: bool}
     */
    public function decide(Principal $principal, int $organizationId, Permission $permission): array
    {
        $roleName = $this->members->activeRole($organizationId, $principal->userId);
        if ($roleName !== null) {
            $role = Role::tryFrom($roleName);
            if ($role === null) {
                return ['outcome' => Outcome::NotFound, 'viaGrant' => false];
            }
            return [
                'outcome' => Policy::roleAllows($role, $permission) ? Outcome::Allow : Outcome::Forbidden,
                'viaGrant' => false,
            ];
        }

        if ($principal->isPlatformAdmin) {
            $levelName = $this->grants->activeLevel($organizationId, $principal->userId, $this->clock->now());
            $level = $levelName === null ? null : GrantLevel::tryFrom($levelName);
            if ($level !== null) {
                return [
                    'outcome' => Policy::grantAllows($level, $permission) ? Outcome::Allow : Outcome::Forbidden,
                    'viaGrant' => true,
                ];
            }
        }

        return ['outcome' => Outcome::NotFound, 'viaGrant' => false];
    }
}
