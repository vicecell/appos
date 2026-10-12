<?php

declare(strict_types=1);

namespace ServerWatch\Core;

use ServerWatch\Auth\Principal;

final class RequestContext
{
    public ?Principal $principal = null;
    /** Tenant resolved AND authorized by the kernel; handlers must use this, never a client-supplied value. */
    public ?int $organizationId = null;

    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly Request $request,
        public readonly array $params,
        public readonly string $requestId,
    ) {
    }

    /**
     * Parsed JSON object body. Enforces content type, size and shape.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $raw = $this->request->rawBody;
        if ($raw === null || $raw === '') {
            return [];
        }
        $type = strtolower(trim(explode(';', $this->request->header('content-type') ?? '')[0]));
        if ($type !== 'application/json') {
            throw new HttpException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
        }
        if (strlen($raw) > Request::MAX_BODY_BYTES) {
            throw new HttpException(413, 'payload_too_large', 'Request body is too large.');
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'invalid_json', 'Request body is not valid JSON.');
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'invalid_json', 'Request body must be a JSON object.');
        }
        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Positive integer route parameter; anything else is simply "not found".
     */
    public function idParam(string $name): int
    {
        $value = $this->params[$name] ?? '';
        if (preg_match('/^[1-9][0-9]{0,17}$/', $value) !== 1) {
            throw HttpException::notFound();
        }
        return (int) $value;
    }

    public function tenantId(): int
    {
        return $this->organizationId ?? throw new \LogicException('Route is not tenant-authorized');
    }
}
