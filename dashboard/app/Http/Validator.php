<?php

declare(strict_types=1);

namespace ServerWatch\Http;

use ServerWatch\Core\HttpException;

/**
 * Strict input validation helpers. Unknown fields are rejected, which also stops clients from smuggling
 * fields such as organization_id into a request.
 */
final class Validator
{
    /**
     * @param array<string, mixed> $body
     * @param list<string> $allowed
     */
    public static function rejectUnknown(array $body, array $allowed): void
    {
        $errors = [];
        foreach (array_keys($body) as $field) {
            if (!in_array((string) $field, $allowed, true)) {
                $errors[(string) $field] = 'Unknown field.';
            }
        }
        if ($errors !== []) {
            throw HttpException::validation($errors);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $errors collected problems, by reference
     */
    public static function string(
        array $body,
        string $field,
        int $min,
        int $max,
        array &$errors,
        bool $required = true,
    ): ?string {
        if (!array_key_exists($field, $body)) {
            if ($required) {
                $errors[$field] = 'Required.';
            }
            return null;
        }
        $value = $body[$field];
        if (!is_string($value)) {
            $errors[$field] = 'Must be a string.';
            return null;
        }
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            $errors[$field] = "Length must be between {$min} and {$max}.";
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $errors[$field] = 'Must not contain control characters.';
            return null;
        }
        return $value;
    }

    public static function hostname(string $value): bool
    {
        if (strlen($value) > 253) {
            return false;
        }
        foreach (explode('.', $value) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }
        return true;
    }
}
