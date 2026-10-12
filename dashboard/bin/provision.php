<?php

declare(strict_types=1);

use ServerWatch\Auth\PasswordHasher;
use ServerWatch\Auth\Provisioner;
use ServerWatch\Auth\SecretBox;
use ServerWatch\Auth\Totp;
use ServerWatch\Authz\GrantLevel;
use ServerWatch\Authz\Role;
use ServerWatch\Core\Config;
use ServerWatch\Core\Db;
use ServerWatch\Core\SystemClock;
use ServerWatch\Repositories\AuditLog;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Operator CLI. There is deliberately no HTTP endpoint for any of this.
 *
 *   php bin/provision.php org <name> <slug>
 *   php bin/provision.php user <name> <email> [--platform-admin] [--no-mfa]   (password: PROVISION_PASSWORD env,
 *                                                                              or generated and printed once)
 *   php bin/provision.php member <org_id> <user_id> <client_admin|operator|viewer>
 *   php bin/provision.php grant <user_id> <org_id> <read|manage> <ttl_seconds> <reason...>
 *   php bin/provision.php revoke-grant <grant_id>
 *
 * The password and TOTP secret of a new user are printed once to stdout and never logged or stored in clear.
 */
$getenv = static fn (string $key): string|false => getenv($key);
$config = Config::fromEnv($getenv);
$db = Db::fromEnv($getenv);
$clock = new SystemClock();
$provisioner = new Provisioner(
    $db,
    new PasswordHasher(),
    new SecretBox($config->appKey),
    $clock,
    new AuditLog($db, $clock),
);

$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$flags = array_values(array_filter($args, static fn (string $a): bool => str_starts_with($a, '--')));
$args = array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')));

$usage = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(2);
};

try {
    switch ($command) {
        case 'org':
            if (count($args) !== 2) {
                $usage('usage: org <name> <slug>');
            }
            echo 'organization id: ' . $provisioner->createOrganization($args[0], $args[1]) . "\n";
            break;

        case 'user':
            if (count($args) !== 2) {
                $usage('usage: user <name> <email> [--platform-admin] [--no-mfa]');
            }
            $password = getenv('PROVISION_PASSWORD');
            $generated = false;
            if ($password === false || $password === '') {
                $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
                $generated = true;
            }
            $platformAdmin = in_array('--platform-admin', $flags, true);
            $user = $provisioner->createUser(
                $args[0],
                $args[1],
                $password,
                $platformAdmin,
                !in_array('--no-mfa', $flags, true),
            );
            echo 'user id: ' . $user['id'] . "\n";
            if ($generated) {
                echo "initial password (shown once): {$password}\n";
            }
            if ($user['totp_secret'] !== null) {
                echo "totp secret (shown once): {$user['totp_secret']}\n";
                echo 'otpauth uri: ' . Totp::provisioningUri($user['totp_secret'], strtolower(trim($args[1])), 'ServerWatch') . "\n";
            }
            break;

        case 'member':
            if (count($args) !== 3 || Role::tryFrom($args[2]) === null) {
                $usage('usage: member <org_id> <user_id> <client_admin|operator|viewer>');
            }
            $provisioner->addMember((int) $args[0], (int) $args[1], Role::from($args[2]));
            echo "member added\n";
            break;

        case 'grant':
            if (count($args) < 5 || GrantLevel::tryFrom($args[2]) === null) {
                $usage('usage: grant <user_id> <org_id> <read|manage> <ttl_seconds> <reason...>');
            }
            $id = $provisioner->grantPlatformAccess(
                (int) $args[0],
                (int) $args[1],
                GrantLevel::from($args[2]),
                implode(' ', array_slice($args, 4)),
                (int) $args[3],
                null,
            );
            echo "grant id: {$id}\n";
            break;

        case 'revoke-grant':
            if (count($args) !== 1) {
                $usage('usage: revoke-grant <grant_id>');
            }
            $provisioner->revokeGrant((int) $args[0], null);
            echo "grant revoked\n";
            break;

        default:
            $usage('commands: org | user | member | grant | revoke-grant');
    }
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");
    exit(1);
}
