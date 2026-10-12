<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Core\RequestContext;
use ServerWatch\Core\Response;
use ServerWatch\Repositories\MemberRepository;

final class MemberController
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function index(RequestContext $ctx): Response
    {
        return Response::json(200, ['data' => $this->members->list($ctx->tenantId())]);
    }
}
