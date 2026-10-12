<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

/**
 * Tenant roles only. "platform_admin" is deliberately NOT a tenant role (see GrantLevel).
 */
enum Role: string
{
    case ClientAdmin = 'client_admin';
    case Operator = 'operator';
    case Viewer = 'viewer';
}
