<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

enum Outcome
{
    case Allow;
    /** Not a member / no grant / org inactive: reported as 404 so tenant existence is not revealed. */
    case NotFound;
    /** Authorized for the tenant but lacking this permission: 403. */
    case Forbidden;
}
