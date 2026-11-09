<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Support;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PixQrProxy
{
    public static function needsProxy(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.svgz?$/i', $path)) {
            return true;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host !== '' && (
            str_contains($host, 'cloudfront.net')
            || str_contains($host, 'vindi.com.br')
            || str_contains($host, 'yapay.com.br')
        )) {
            return true;
        }

        return false;
    }

    public static function fetchAndStream(string $url): void
    {
        $ch = curl_init($url);
        if ($ch === false) {
            http_response_code(502);
            exit;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'PrestaShop / PB Connect PIX QR',
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $remoteType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300 || $body === '') {
            http_response_code(502);
            exit;
        }

        $contentType = self::detectContentType($url, $body, $remoteType);
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }

    private static function detectContentType(string $url, string $body, string $remoteContentType): string
    {
        $trimmed = ltrim($body);
        if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<svg')) {
            return 'image/svg+xml';
        }
        if (str_starts_with($body, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        $remote = strtolower(trim($remoteContentType));
        if ($remote !== '' && str_starts_with($remote, 'image/')) {
            $parts = explode(';', $remote, 2);

            return $parts[0] !== '' ? $parts[0] : 'image/png';
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.svgz?$/i', $path)) {
            return 'image/svg+xml';
        }
        if (preg_match('/\.jpe?g$/i', $path)) {
            return 'image/jpeg';
        }

        return 'image/png';
    }
}
