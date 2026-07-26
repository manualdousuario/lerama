<?php

namespace App\Support;

// Anti-SSRF URL validation.
class UrlValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @return array{valid: bool, error?: string}
     */
    public static function validate(string $url, bool $checkDns = false): array
    {
        if (empty($url)) {
            return ['valid' => false, 'error' => 'URL is empty'];
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return ['valid' => false, 'error' => 'Only HTTP and HTTPS URLs are allowed'];
        }

        $host = self::normalizeHost($parsed['host']);

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return ['valid' => false, 'error' => 'Localhost URLs are not allowed'];
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['valid' => false, 'error' => 'Private or reserved IP addresses are not allowed'];
        }

        if ($checkDns) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            if ($records === false) {
                return ['valid' => false, 'error' => 'Could not resolve hostname'];
            }
            foreach ($records as $record) {
                $resolvedIp = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($resolvedIp !== null && ! filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return ['valid' => false, 'error' => 'Hostname resolves to a private or reserved IP address'];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validates a URL used for public redirects (random/shuffle).
     */
    public static function validateRedirectUrl(string $url): bool
    {
        return self::validate($url)['valid'];
    }

    /**
     * Resolvers accept more than dotted-quad notation: bracketed IPv6, plain
     * decimal (2130706433) and octal/hex octets (0177.0.0.1) all reach
     * 127.0.0.1. Fold them into a canonical form before the range checks.
     */
    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host, '[]'));

        if (ctype_digit($host)) {
            $value = (int) $host;
            if ($value >= 0 && $value <= 4294967295) {
                return long2ip($value);
            }
        }

        $octet = '(?:0[xX][0-9a-fA-F]+|0[0-7]*|\d+)';
        if (preg_match("/^{$octet}(?:\.{$octet}){3}$/", $host)) {
            $parts = array_map(static fn (string $p): int => (int) intval($p, 0), explode('.', $host));
            if (! array_filter($parts, static fn (int $p): bool => $p < 0 || $p > 255)) {
                return implode('.', $parts);
            }
        }

        return $host;
    }
}
