<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Auth\Csrf;
use ServerWatch\Auth\Principal;
use ServerWatch\Auth\SessionStore;
use ServerWatch\Authz\Authorizer;
use ServerWatch\Authz\Outcome;
use ServerWatch\Authz\Permission;
use ServerWatch\Core\Config;
use ServerWatch\Core\Request;
use ServerWatch\Repositories\AuditLog;

/**
 * Glue the kernel calls for protected routes: session lookup, CSRF check, tenant authorization + auditing.
 */
final class Guard
{
    public function __construct(
        private readonly Config $config,
        private readonly SessionStore $sessions,
        private readonly Csrf $csrf,
        private readonly Authorizer $authorizer,
        private readonly AuditLog $audit,
    ) {
    }

    public function sessionToken(Request $request): ?string
    {
        $token = $request->cookies[$this->config->cookieName()] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    public function authenticate(Request $request): ?Principal
    {
        $token = $this->sessionToken($request);
        return $token === null ? null : $this->sessions->resolve($token);
    }

    public function csrfValid(Request $request): bool
    {
        $token = $this->sessionToken($request);
        return $token !== null && $this->csrf->isValid($token, $request->header('x-csrf-token'));
    }

    public function authorize(Principal $principal, int $organizationId, Permission $permission, string $ip): Outcome
    {
        $decision = $this->authorizer->decide($principal, $organizationId, $permission);

        if ($decision['outcome'] === Outcome::Allow && $decision['viaGrant']) {
            $this->audit->record(
                $organizationId,
                $principal->userId,
                'platform.cross_tenant_access',
                'organization',
                (string) $organizationId,
                $ip,
                ['permission' => $permission->value],
            );
        }
        if ($decision['outcome'] !== Outcome::Allow) {
            // Recorded without an organization id on purpose: a hostile user must not be able to write into a
            // victim tenant's audit trail. The targeted id is kept in target_id for investigators.
            $this->audit->record(
                null,
                $principal->userId,
                'authz.denied',
                'organization',
                (string) $organizationId,
                $ip,
                ['permission' => $permission->value, 'outcome' => $decision['outcome'] === Outcome::Forbidden ? 'forbidden' : 'not_found'],
            );
        }
        return $decision['outcome'];
    }
}
