<?php

declare(strict_types=1);

namespace ServerWatch\Authz;

enum Permission: string
{
    case ServersRead = 'servers.read';
    case ServersWrite = 'servers.write';
    case MembersRead = 'members.read';

    public function isRead(): bool
    {
        return str_ends_with($this->value, '.read');
    }
}
