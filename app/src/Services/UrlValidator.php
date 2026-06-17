<?php

declare(strict_types=1);

namespace Lerama\Services;

class UrlValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Validate that a URL is safe for the application to use.
     *
     * @param string $url URL to validate
     * @param bool $checkDns Whether to resolve the hostname and validate the IP
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
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return ['valid' => false, 'error' => 'Only HTTP and HTTPS URLs are allowed'];
        }

        $host = strtolower($parsed['host']);

        // Reject localhost and common internal hostnames
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return ['valid' => false, 'error' => 'Localhost URLs are not allowed'];
        }

        // Reject IP-based URLs that are private/reserved
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['valid' => false, 'error' => 'Private or reserved IP addresses are not allowed'];
        }

        if ($checkDns) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            if ($records === false) {
                return ['valid' => false, 'error' => 'Could not resolve hostname'];
            }
            foreach ($records as $record) {
                $resolvedIp = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($resolvedIp !== null && !filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return ['valid' => false, 'error' => 'Hostname resolves to a private or reserved IP address'];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate a URL for public redirection (random/shuffle).
     */
    public static function validateRedirectUrl(string $url): bool
    {
        $result = self::validate($url);
        return $result['valid'];
    }
}
