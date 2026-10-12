<?php

declare(strict_types=1);

namespace ServerWatch\Repositories;

/**
 * Marker for repositories whose data belongs to one organization. Every public method MUST take
 * `int $organizationId` as its first parameter (enforced by tests/Unit/RepositoryScopeTest).
 */
interface TenantScoped
{
}
