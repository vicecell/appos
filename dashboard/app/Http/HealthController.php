<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Core\Response;

final class HealthController
{
    public function show(): Response
    {
        return Response::json(200, ['status' => 'ok', 'service' => 'serverwatch-dashboard']);
    }
}
