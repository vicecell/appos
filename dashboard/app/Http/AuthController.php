<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Auth\AuthService;
use ServerWatch\Auth\Csrf;
use ServerWatch\Core\Config;
use ServerWatch\Core\HttpException;
use ServerWatch\Core\RequestContext;
use ServerWatch\Core\Response;
use ServerWatch\Auth\SessionStore;
use ServerWatch\Repositories\AuditLog;
use ServerWatch\Repositories\UserRepository;

final class AuthController
{
    public function __construct(
        private readonly Config $config,
        private readonly AuthService $auth,
        private readonly SessionStore $sessions,
        private readonly Csrf $csrf,
        private readonly UserRepository $users,
        private readonly AuditLog $audit,
        private readonly Guard $guard,
    ) {
    }

    public function login(RequestContext $ctx): Response
    {
        $body = $ctx->body();
        Validator::rejectUnknown($body, ['email', 'password', 'totp_code']);

        $errors = [];
        $email = Validator::string($body, 'email', 1, 254, $errors);
        $password = $body['password'] ?? null;
        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Required.';
        } elseif (strlen($password) > 1024) {
            $errors['password'] = 'Too long.';
        }
        $totp = null;
        if (array_key_exists('totp_code', $body)) {
            $totp = $body['totp_code'];
            if (!is_string($totp) || preg_match('/^[0-9]{6}$/', $totp) !== 1) {
                $errors['totp_code'] = 'Must be a 6-digit code.';
            }
        }
        if ($errors !== [] || $email === null || !is_string($password)) {
            throw HttpException::validation($errors);
        }

        $result = $this->auth->login($email, $password, is_string($totp) ? $totp : null, $ctx->request->ip);
        $profile = $this->users->publicProfile($result['userId']);

        return Response::json(200, ['data' => [
            'user' => $profile,
            'csrf_token' => $this->csrf->tokenFor($result['token']),
        ]])->withHeader('Set-Cookie', $this->config->sessionCookie($result['token'], $this->config->sessionAbsoluteSeconds));
    }

    public function logout(RequestContext $ctx): Response
    {
        $token = $this->guard->sessionToken($ctx->request);
        if ($token !== null) {
            $this->sessions->revoke($token);
        }
        $this->audit->record(null, $ctx->principal?->userId, 'logout', null, null, $ctx->request->ip);

        return Response::json(200, ['data' => ['ok' => true]])
            ->withHeader('Set-Cookie', $this->config->sessionCookie('', 0));
    }

    public function me(RequestContext $ctx): Response
    {
        $principal = $ctx->principal ?? throw new \LogicException('me() requires authentication');
        $token = $this->guard->sessionToken($ctx->request) ?? '';

        return Response::json(200, ['data' => [
            'user' => $this->users->publicProfile($principal->userId),
            'csrf_token' => $this->csrf->tokenFor($token),
            'memberships' => $this->users->activeMemberships($principal->userId),
        ]]);
    }
}
