<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Api;

use PbConnect\Security\CredentialVault;
use PbConnect\Support\ApiRequestLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PbClient
{
    private const BASE_URL = 'https://api.pbintegracoes.com/v1/';

    private string $moduleVersion;

    private ?string $connectKeyOverride = null;

    public function __construct(string $moduleVersion = '5.0.0', ?string $connectKey = null)
    {
        $this->moduleVersion = $moduleVersion;
        $this->connectKeyOverride = $connectKey;
    }

    /**
     * @throws \RuntimeException when the key is rejected
     */
    public static function assertConnectKeyValid(string $connectKey, string $moduleVersion = '5.0.0'): array
    {
        $normalized = CredentialVault::normalizeConnectKey($connectKey);
        if ($normalized === '') {
            throw new \RuntimeException('Connect Key is empty.');
        }
        if (!CredentialVault::isVindiConnectKey($normalized)) {
            throw new \RuntimeException('Connect Key must start with CONVD or CONVDSANDBOX.');
        }

        return (new self($moduleVersion, $normalized))->connectInfo();
    }

    public function connectInfo(): array
    {
        return $this->request('GET', 'connectInfo');
    }

    public function createOrder(array $body): array
    {
        return $this->request('POST', 'orders?debug=1', $body, [201]);
    }

    public function getOrder(string $orderId): array
    {
        return $this->request('GET', 'orders/' . rawurlencode($orderId));
    }

    public function createCheckout(array $body): array
    {
        return $this->request('POST', 'checkouts', $body, [201]);
    }

    public function getCheckout(string $checkoutId): array
    {
        return $this->request('GET', 'checkouts/' . rawurlencode($checkoutId));
    }

    /**
     * @param array<string, scalar> $query
     */
    public function feesCalculate(array $query): array
    {
        return $this->request('GET', 'charges/fees/calculate?' . http_build_query($query));
    }

    public function tokenizeConfig(): array
    {
        return $this->request('GET', 'tokenize/config');
    }

    public function createPaymentProfile(array $body): array
    {
        return $this->request('POST', 'payment_profiles', $body, [200, 201]);
    }

    public function paymentMethods(?string $query = null): array
    {
        $path = 'payment_methods';
        if ($query !== null && $query !== '') {
            $path .= '?' . (str_contains($query, '=') ? $query : 'query=' . rawurlencode($query));
        }

        return $this->request('GET', $path);
    }

    public function threeDsSetup(array $body): array
    {
        return $this->request('POST', 'three-d-secure/sessions/setup', $body, [200, 201]);
    }

    public function threeDsEnroll(array $body): array
    {
        return $this->request('POST', 'three-d-secure/sessions/enroll', $body);
    }

    public function threeDsValidate(array $body): array
    {
        return $this->request('POST', 'three-d-secure/sessions/validate', $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param list<int>|null $okStatuses
     */
    private function request(string $method, string $path, ?array $body = null, ?array $okStatuses = null): array
    {
        $key = $this->connectKeyOverride ?? CredentialVault::getConnectKey();
        if ($key === '') {
            throw new \RuntimeException('Connect Key is not configured.');
        }

        $url = self::BASE_URL . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
            'Platform: PrestaShop',
            'Platform-Version: ' . _PS_VERSION_,
            'Module-Version: ' . $this->moduleVersion,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            ApiRequestLogger::log($method, $path, 0, $body, null, $error);
            throw new \RuntimeException('API request failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $response];
        }

        ApiRequestLogger::log($method, $path, $httpCode, $body, $decoded);

        if ($httpCode >= 400) {
            throw new \RuntimeException(self::formatApiError($decoded, $httpCode), $httpCode);
        }

        $allowed = $okStatuses ?? [200, 201];
        if (!in_array($httpCode, $allowed, true) && $httpCode >= 300) {
            throw new \RuntimeException('Unexpected HTTP ' . $httpCode, $httpCode);
        }

        return $decoded;
    }

    /**
     * Prefer partner/upstream detail over the generic "3DS upstream error" wrapper.
     *
     * @param array<string, mixed> $decoded
     */
    private static function formatApiError(array $decoded, int $httpCode): string
    {
        // Prefer PB-normalized message (already humanized, e.g. "CPF/CNPJ inválido") over raw partner jargon.
        $top = isset($decoded['message']) && is_string($decoded['message'])
            ? trim($decoded['message'])
            : '';
        if ($top !== '') {
            return $top;
        }

        $parts = [];
        $upstream = is_array($decoded['upstream'] ?? null) ? $decoded['upstream'] : null;
        $upBody = is_array($upstream['body'] ?? null) ? $upstream['body'] : null;
        if (is_array($upBody)) {
            $errors = $upBody['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
                $msg = (string) ($errors[0]['message'] ?? $errors[0]['code'] ?? '');
                $details = (string) ($errors[0]['details'] ?? '');
                if ($msg !== '') {
                    $parts[] = $details !== '' ? ($msg . ': ' . $details) : $msg;
                }
            } elseif (isset($upBody['message']) && is_string($upBody['message'])) {
                $parts[] = $upBody['message'];
            }
        }

        if ($parts === []) {
            $fallback = $decoded['error_messages'][0]['description']
                ?? $decoded['error']
                ?? ('HTTP ' . $httpCode);
            $parts[] = (string) $fallback;
        }

        return implode(' ', $parts);
    }
}
