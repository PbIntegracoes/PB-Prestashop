<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Security;

use PbConnect\Config\ConfigurationKeys;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CredentialVault
{
    public static function normalizeConnectKey(string $plainKey): string
    {
        $key = trim($plainKey);
        if ($key === '' || $key === '********') {
            return '';
        }

        if (stripos($key, 'bearer ') === 0) {
            $key = trim(substr($key, 7));
        }

        return preg_replace('/\s+/', '', $key) ?? '';
    }

    public static function storeConnectKey(string $plainKey): void
    {
        $normalized = self::normalizeConnectKey($plainKey);
        if ($normalized === '') {
            return;
        }

        if (!self::isVindiConnectKey($normalized)) {
            throw new \InvalidArgumentException('Connect Key must start with CONVD or CONVDSANDBOX (API v1).');
        }

        \Configuration::updateValue(ConfigurationKeys::CONNECT_KEY, self::encrypt($normalized));
    }

    public static function getConnectKey(): string
    {
        $stored = (string) \Configuration::get(ConfigurationKeys::CONNECT_KEY);
        if ($stored === '') {
            return '';
        }

        return self::decrypt($stored);
    }

    public static function hasConnectKey(): bool
    {
        return self::getConnectKey() !== '';
    }

    public static function isVindiConnectKey(string $key): bool
    {
        $key = self::normalizeConnectKey($key);

        return stripos($key, 'CONVDSANDBOX') === 0 || stripos($key, 'CONVD') === 0;
    }

    public static function isSandbox(): bool
    {
        $key = self::getConnectKey();

        return stripos($key, 'CONVDSANDBOX') === 0;
    }

    /**
     * Short HMAC for notification URLs. Prefer cart id (order may not exist yet at API create).
     */
    public static function webhookHash(int $idCart): string
    {
        $key = self::getConnectKey();
        if ($key === '') {
            return '';
        }

        return substr(hash_hmac('sha256', 'pbconnect-notif|' . $key, (string) $idCart), 0, 4);
    }

    public static function validateWebhookHash(int $idCart, string $hash): bool
    {
        $expected = self::webhookHash($idCart);

        return $expected !== '' && hash_equals($expected, $hash);
    }

    private static function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return base64_encode($value);
    }

    private static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = base64_decode($value, true);

        return $decoded !== false ? $decoded : '';
    }
}
