<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

/**
 * Scope of an explicit, time-boxed platform-admin grant on one tenant.
 */
enum GrantLevel: string
{
    case Read = 'read';
    case Manage = 'manage';
}
