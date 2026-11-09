<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Payment;

use PbConnect\Config\ConfigurationKeys;
use PbConnect\Config\InstallmentMode;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class FeesCalculateQueryBuilder
{
    /**
     * @return array<string, int|string>
     */
    public static function build(int $valueCents, string $creditCardBin): array
    {
        $valueCents = max(100, $valueCents);
        $bin = preg_replace('/\D/', '', $creditCardBin) ?: '555566';
        if (strlen($bin) < 6) {
            $bin = '555566';
        }

        $maxInstallments = max(1, (int) \Configuration::get(ConfigurationKeys::CC_MAX_INSTALLMENTS) ?: 12);
        $mode = InstallmentMode::resolveStoredMode();

        $query = [
            'payment_methods' => 'CREDIT_CARD',
            'value' => $valueCents,
            'credit_card_bin' => substr($bin, 0, 6),
            'max_installments' => $maxInstallments,
        ];

        $maxNoInterest = self::resolveMaxInstallmentsNoInterest($mode, $valueCents, $maxInstallments);
        if ($maxNoInterest !== null) {
            $query['max_installments_no_interest'] = $maxNoInterest;
        }

        return $query;
    }

    private static function resolveMaxInstallmentsNoInterest(
        string $mode,
        int $valueCents,
        int $maxInstallments,
    ): ?int {
        switch ($mode) {
            case InstallmentMode::BUYER_ALWAYS:
                // API: 0 = só 1x sem juros; 1 é proibido. Woo: buyer → 0.
                return 0;

            case InstallmentMode::MERCHANT_UPTO_N:
                return max(1, min($maxInstallments, (int) \Configuration::get(ConfigurationKeys::CC_MAX_NO_INTEREST) ?: 3));

            case InstallmentMode::MERCHANT_BY_MIN_INSTALLMENT:
                $minCents = max(500, (int) \Configuration::get(ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS) ?: 5000);
                $derived = (int) floor($valueCents / $minCents);

                return max(1, min($maxInstallments, $derived));

            default:
                return max(1, (int) \Configuration::get(ConfigurationKeys::CC_MAX_NO_INTEREST) ?: 3);
        }
    }
}
