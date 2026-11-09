<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class InstallmentMode
{
    /**
     * Legacy PagBank “account promotions / external” — not available on Vindi.
     * Kept only so old config values map to {@see BUYER_ALWAYS}.
     */
    public const ACCOUNT_PROMOTIONS = 'account_promotions';

    public const BUYER_ALWAYS = 'buyer_always';

    public const MERCHANT_UPTO_N = 'merchant_upto_n';

    public const MERCHANT_BY_MIN_INSTALLMENT = 'merchant_by_min_installment';

    /**
     * Modes shown in admin (Vindi).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BUYER_ALWAYS,
            self::MERCHANT_UPTO_N,
            self::MERCHANT_BY_MIN_INSTALLMENT,
        ];
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::all(), true);
    }

    public static function resolveStoredMode(): string
    {
        $stored = (string) \Configuration::get(ConfigurationKeys::CC_INSTALLMENT_MODE);
        // PagBank “promoções da conta / external” → juros com o comprador (como no Woo).
        if ($stored === self::ACCOUNT_PROMOTIONS || $stored === 'external') {
            return self::BUYER_ALWAYS;
        }
        if ($stored !== '' && self::isValid($stored)) {
            return $stored;
        }

        return self::MERCHANT_UPTO_N;
    }
}
