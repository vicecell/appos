<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Core\HttpException;
use ServerWatch\Core\RequestContext;
use ServerWatch\Core\Response;
use ServerWatch\Repositories\AuditLog;
use ServerWatch\Repositories\ServerRepository;

/**
 * Tenant scope always comes from $ctx->tenantId(), which the kernel set after authorization. The request body
 * and query string are never consulted for it.
 */
final class ServerController
{
    public function __construct(
        private readonly ServerRepository $servers,
        private readonly AuditLog $audit,
    ) {
    }

    public function index(RequestContext $ctx): Response
    {
        $errors = [];
        $limit = 50;
        $rawLimit = $ctx->request->query['limit'] ?? null;
        if ($rawLimit !== null) {
            if (preg_match('/^[0-9]{1,3}$/', $rawLimit) !== 1 || (int) $rawLimit < 1 || (int) $rawLimit > 100) {
                $errors['limit'] = 'Must be an integer between 1 and 100.';
            } else {
                $limit = (int) $rawLimit;
            }
        }
        $after = 0;
        $rawCursor = $ctx->request->query['cursor'] ?? null;
        if ($rawCursor !== null) {
            if (preg_match('/^[1-9][0-9]{0,17}$/', $rawCursor) !== 1) {
                $errors['cursor'] = 'Invalid cursor.';
            } else {
                $after = (int) $rawCursor;
            }
        }
        if ($errors !== []) {
            throw HttpException::validation($errors);
        }

        $page = $this->servers->list($ctx->tenantId(), $limit, $after);
        return Response::json(200, ['data' => $page['items'], 'next_cursor' => $page['next_cursor']]);
    }

    public function show(RequestContext $ctx): Response
    {
        $server = $this->servers->find($ctx->tenantId(), $ctx->idParam('id'));
        if ($server === null) {
            throw HttpException::notFound();
        }
        return Response::json(200, ['data' => $server]);
    }

    public function create(RequestContext $ctx): Response
    {
        $body = $ctx->body();
        Validator::rejectUnknown($body, ['name', 'hostname']);

        $errors = [];
        $name = Validator::string($body, 'name', 1, 100, $errors);
        $hostname = Validator::string($body, 'hostname', 1, 253, $errors);
        if ($hostname !== null) {
            $hostname = strtolower($hostname);
            if (!Validator::hostname($hostname)) {
                $errors['hostname'] = 'Must be a valid DNS hostname.';
            }
        }
        if ($errors !== [] || $name === null || $hostname === null) {
            throw HttpException::validation($errors);
        }

        $server = $this->servers->create($ctx->tenantId(), $name, $hostname);
        $this->audit->record(
            $ctx->tenantId(),
            $ctx->principal?->userId,
            'server.create',
            'server',
            (string) $server['id'],
            $ctx->request->ip,
        );
        return Response::json(201, ['data' => $server]);
    }

    public function update(RequestContext $ctx): Response
    {
        $id = $ctx->idParam('id');
        $body = $ctx->body();
        Validator::rejectUnknown($body, ['name']);

        $errors = [];
        $name = Validator::string($body, 'name', 1, 100, $errors);
        if ($errors !== [] || $name === null) {
            throw HttpException::validation($errors === [] ? ['name' => 'Required.'] : $errors);
        }

        $server = $this->servers->rename($ctx->tenantId(), $id, $name);
        if ($server === null) {
            throw HttpException::notFound();
        }
        $this->audit->record(
            $ctx->tenantId(),
            $ctx->principal?->userId,
            'server.update',
            'server',
            (string) $id,
            $ctx->request->ip,
            ['fields' => ['name']],
        );
        return Response::json(200, ['data' => $server]);
    }
}
