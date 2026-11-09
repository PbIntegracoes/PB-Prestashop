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

/**
 * Amount helpers — validateOrder must use the external system value when available.
 */
final class GatewayAmount
{
    /**
     * Extract paid/charged amount in major currency units (BRL) from API v1 response.
     */
    public static function toFloat(array $response): float
    {
        $cents = self::toCents($response);
        if ($cents <= 0) {
            return 0.0;
        }

        return round($cents / 100, 2);
    }

    public static function toCents(array $response): int
    {
        if (!empty($response['charges'][0]['amount']['value'])) {
            return (int) $response['charges'][0]['amount']['value'];
        }
        if (!empty($response['qr_codes'][0]['amount']['value'])) {
            return (int) $response['qr_codes'][0]['amount']['value'];
        }
        if (!empty($response['amount']['value'])) {
            return (int) $response['amount']['value'];
        }
        // Checkout session
        if (!empty($response['items']) && is_array($response['items'])) {
            $sum = 0;
            foreach ($response['items'] as $item) {
                $unit = (int) ($item['unit_amount'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $sum += $unit * $qty;
            }
            if (!empty($response['shipping']['amount'])) {
                $sum += (int) $response['shipping']['amount'];
            }
            if ($sum > 0) {
                return $sum;
            }
        }

        return 0;
    }
}
