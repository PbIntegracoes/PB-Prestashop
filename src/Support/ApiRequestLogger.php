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

use PbConnect\Config\ConfigurationKeys;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * File log under var/logs/pbintegracoes.log (when Debug logs is enabled).
 */
final class ApiRequestLogger
{
    private const FILENAME = 'pbintegracoes.log';

    public static function enabled(): bool
    {
        return \Configuration::get(ConfigurationKeys::DEBUG_MODE) === '1';
    }

    /**
     * @param array<string, mixed>|null $requestBody
     * @param array<string, mixed>|string|null $responseBody
     */
    public static function log(
        string $method,
        string $path,
        int $httpCode,
        ?array $requestBody = null,
        $responseBody = null,
        ?string $error = null,
    ): void {
        if (!self::enabled()) {
            return;
        }

        $dir = _PS_ROOT_DIR_ . '/var/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . self::FILENAME;

        $line = [
            'ts' => date('c'),
            'method' => $method,
            'path' => self::redactPath($path),
            'http' => $httpCode,
        ];
        if ($error !== null && $error !== '') {
            $line['error'] = $error;
        }
        if ($requestBody !== null) {
            $line['request'] = self::redactPayload($requestBody);
        }
        if ($responseBody !== null) {
            $line['response'] = is_array($responseBody)
                ? self::redactPayload($responseBody)
                : self::truncate((string) $responseBody, 4000);
        }

        $encoded = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($file, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function redactPath(string $path): string
    {
        // Keep query keys, drop values that look like secrets.
        return preg_replace('/(access_token|token|key)=[^&]*/i', '$1=***', $path) ?? $path;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $parentKey lowercase parent key (card context redacts pan `number`)
     *
     * @return array<string, mixed>
     */
    private static function redactPayload(array $data, string $parentKey = ''): array
    {
        // Do not blanket-redact `number`: phones[].number and address.number are not secrets.
        // Only redact `number` under card-like parents (PAN).
        $alwaysSensitive = [
            'gateway_token',
            'access_token',
            'encrypted',
            'security_code',
            'cvv',
            'card_number',
            'authorization',
        ];
        $cardParents = ['card', 'cards', 'payment_method', 'credit_card'];
        $out = [];
        foreach ($data as $key => $value) {
            $k = strtolower((string) $key);
            $isCardPan = $k === 'number' && in_array($parentKey, $cardParents, true);
            if ($isCardPan
                || in_array($k, $alwaysSensitive, true)
                || str_contains($k, 'token')
                || str_contains($k, 'password')
                || str_contains($k, 'secret')
            ) {
                $out[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::redactPayload($value, $k);
                continue;
            }
            if (is_string($value) && strlen($value) > 2000) {
                $out[$key] = self::truncate($value, 2000);
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max) . '…';
    }
}
