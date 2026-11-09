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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Opaque token for FO payment / AJAX callbacks (checklist: signature or token).
 */
final class PaymentRequestToken
{
    private const CONFIG_SECRET = 'PBCONNECT_REQUEST_SECRET';

    public static function ensureSecret(): string
    {
        $secret = (string) \Configuration::get(self::CONFIG_SECRET);
        if ($secret === '') {
            $secret = \Tools::passwdGen(48);
            \Configuration::updateValue(self::CONFIG_SECRET, $secret);
        }

        return $secret;
    }

    public static function forCart(int $idCart, int $shopId = 0): string
    {
        $secret = self::ensureSecret();

        return hash_hmac('sha256', 'pbconnect|' . $shopId . '|' . $idCart, $secret);
    }

    public static function validate(int $idCart, string $token, int $shopId = 0): bool
    {
        if ($idCart <= 0 || $token === '') {
            return false;
        }

        $expected = self::forCart($idCart, $shopId);

        return hash_equals($expected, $token);
    }
}
