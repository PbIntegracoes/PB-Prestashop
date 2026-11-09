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

use PbConnect\Security\CredentialVault;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Admin deep-links to Vindi / Yapay (same URLs as Woo Branding).
 */
final class PartnerPortal
{
    public static function vindiBillUrl(string $billId, ?bool $sandbox = null): ?string
    {
        $billId = trim($billId);
        if ($billId === '') {
            return null;
        }
        $host = self::isSandbox($sandbox) ? 'sandbox-app.vindi.com.br' : 'app.vindi.com.br';

        return 'https://' . $host . '/admin/bills/' . rawurlencode($billId);
    }

    public static function vindiChargeUrl(string $chargeId, ?bool $sandbox = null): ?string
    {
        $chargeId = trim($chargeId);
        if ($chargeId === '') {
            return null;
        }
        $host = self::isSandbox($sandbox) ? 'sandbox-app.vindi.com.br' : 'app.vindi.com.br';

        return 'https://' . $host . '/admin/charges/' . rawurlencode($chargeId);
    }

    /**
     * Prefer bill (PIX/boleto unpaid OK); charge as fallback.
     */
    public static function vindiPortalUrl(?string $billId, ?string $chargeId, ?bool $sandbox = null): ?string
    {
        $bill = self::vindiBillUrl((string) $billId, $sandbox);
        if ($bill !== null) {
            return $bill;
        }

        return self::vindiChargeUrl((string) $chargeId, $sandbox);
    }

    public static function yapaySaleUrl(string $transactionId, ?bool $sandbox = null): ?string
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return null;
        }
        $host = self::isSandbox($sandbox)
            ? 'intermediador-sandbox.yapay.com.br'
            : 'intermediador.yapay.com.br';

        return 'https://' . $host . '/orders/' . rawurlencode($transactionId) . '/sale';
    }

    private static function isSandbox(?bool $sandbox): bool
    {
        return $sandbox ?? CredentialVault::isSandbox();
    }
}
