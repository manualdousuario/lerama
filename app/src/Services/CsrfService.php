<?php

declare(strict_types=1);

namespace Lerama\Services;

class CsrfService
{
    private const TOKEN_KEY = 'csrf_token';

    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function generateToken(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function getToken(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::TOKEN_KEY] ?? null;
    }

    public static function validateToken(?string $token): bool
    {
        self::ensureSession();
        $expected = $_SESSION[self::TOKEN_KEY] ?? null;
        if (empty($expected) || empty($token)) {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public static function regenerateToken(): string
    {
        self::ensureSession();
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::TOKEN_KEY];
    }
}
