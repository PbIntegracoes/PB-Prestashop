<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Admin;

use PbConnect\Support\PartnerPortal;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Presenter for admin order payment details (payload → UI + OrderPayment sync).
 */
final class OrderPaymentView
{
    /**
     * @param array<string, mixed> $payload API order JSON
     *
     * @return array{
     *   method: string,
     *   status: string,
     *   pb_order_id: string,
     *   pb_charge_id: string,
     *   partner_bill_id: string,
     *   yapay_transaction_id: string,
     *   amount_cents: int,
     *   installments: int,
     *   card_brand: string,
     *   card_brand_key: string,
     *   card_brand_label: string,
     *   card_first: string,
     *   card_last: string,
     *   card_holder: string,
     *   card_expiration: string,
     *   card_masked: string,
     *   is_card: bool,
     *   three_ds: string,
     *   vindi_url: ?string,
     *   yapay_url: ?string
     * }
     */
    public static function fromPayload(array $payload, string $fallbackMethod = '', string $fallbackStatus = ''): array
    {
        $charge = is_array($payload['charges'][0] ?? null) ? $payload['charges'][0] : [];
        $pm = is_array($charge['payment_method'] ?? null) ? $charge['payment_method'] : [];
        $card = is_array($pm['card'] ?? null) ? $pm['card'] : [];
        $holder = is_array($card['holder'] ?? null) ? $card['holder'] : [];
        $company = is_array($card['payment_company'] ?? null) ? $card['payment_company'] : [];
        $paymentResponse = is_array($charge['payment_response'] ?? null) ? $charge['payment_response'] : [];
        $raw = is_array($paymentResponse['raw_data'] ?? null) ? $paymentResponse['raw_data'] : [];

        $method = (string) ($pm['type'] ?? $fallbackMethod);
        $status = strtoupper((string) ($charge['status'] ?? $payload['status'] ?? $fallbackStatus));
        $installments = max(0, (int) ($pm['installments'] ?? 0));

        $brand = (string) ($card['brand'] ?? $company['code'] ?? $company['name'] ?? '');
        $first = (string) ($card['first_digits'] ?? '');
        $last = (string) ($card['last_digits'] ?? '');
        $cardHolder = (string) ($holder['name'] ?? '');
        $expMonth = (string) ($card['exp_month'] ?? $card['expiration_month'] ?? '');
        $expYear = (string) ($card['exp_year'] ?? $card['expiration_year'] ?? '');
        $expiration = self::formatExpiry($expMonth, $expYear);

        $billId = (string) ($payload['partner_bill_id'] ?? $charge['partner_bill_id'] ?? '');
        if ($billId === '' && isset($payload['partner_bill_id'])) {
            $billId = (string) $payload['partner_bill_id'];
        }
        $chargeId = (string) ($charge['id'] ?? $payload['charges'][0]['id'] ?? '');
        $pbOrderId = (string) ($payload['id'] ?? '');
        $yapayTxn = (string) ($raw['transaction_id'] ?? $paymentResponse['transaction_id'] ?? '');

        $isCard = stripos($method, 'CREDIT_CARD') !== false
            || $brand !== ''
            || $last !== '';

        $threeDs = '';
        if (stripos($method, '3DS') !== false) {
            $three = is_array($pm['three_ds'] ?? null) ? $pm['three_ds'] : [];
            $threeDs = (string) ($three['status'] ?? 'AUTHENTICATED');
        }

        $masked = '';
        if ($first !== '' || $last !== '') {
            $masked = ($first !== '' ? $first : '******') . '•• ••••' . ($last !== '' ? $last : '••••');
        }

        return [
            'method' => $method !== '' ? $method : $fallbackMethod,
            'status' => $status !== '' ? $status : $fallbackStatus,
            'pb_order_id' => $pbOrderId,
            'pb_charge_id' => $chargeId,
            'partner_bill_id' => $billId,
            'yapay_transaction_id' => $yapayTxn,
            'amount_cents' => (int) ($charge['amount']['value'] ?? $payload['charges'][0]['amount']['value'] ?? 0),
            'installments' => $installments,
            'card_brand' => $brand,
            'card_brand_key' => self::normalizeBrandKey($brand),
            'card_brand_label' => self::brandLabel($brand),
            'card_first' => $first,
            'card_last' => $last,
            'card_holder' => $cardHolder,
            'card_expiration' => $expiration,
            'card_masked' => $masked,
            'is_card' => $isCard,
            'three_ds' => $threeDs,
            'vindi_url' => PartnerPortal::vindiPortalUrl($billId, $chargeId),
            'yapay_url' => PartnerPortal::yapaySaleUrl($yapayTxn),
        ];
    }

    /**
     * Prefer charge id / bill id / yapay txn as PrestaShop transaction_id.
     *
     * @param array<string, mixed> $view
     */
    public static function preferredTransactionId(array $view): string
    {
        foreach (['pb_charge_id', 'partner_bill_id', 'yapay_transaction_id', 'pb_order_id'] as $key) {
            $v = trim((string) ($view[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    private static function formatExpiry(string $month, string $year): string
    {
        $month = preg_replace('/\D+/', '', $month) ?? '';
        $year = preg_replace('/\D+/', '', $year) ?? '';
        if ($month === '' || $year === '') {
            return '';
        }
        if (strlen($month) === 1) {
            $month = '0' . $month;
        }
        if (strlen($year) === 4) {
            $year = substr($year, -2);
        }

        return $month . '/' . $year;
    }

    /** Map API brand codes to SVG basename keys under views/img/credit-cards/. */
    public static function normalizeBrandKey(string $brand): string
    {
        $b = strtolower(trim(str_replace([' ', '-'], '_', $brand)));
        return match ($b) {
            'visa' => 'visa',
            'mastercard', 'master', 'master_card' => 'mastercard',
            'amex', 'american_express', 'americanexpress' => 'amex',
            'elo' => 'elo',
            'hipercard', 'hiper' => 'hipercard',
            'diners', 'diners_club', 'dinersclub' => 'diners',
            'jcb' => 'jcb',
            default => $b,
        };
    }

    public static function brandLabel(string $brand): string
    {
        return match (self::normalizeBrandKey($brand)) {
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'elo' => 'Elo',
            'hipercard' => 'Hipercard',
            'diners' => 'Diners Club',
            'jcb' => 'JCB',
            '' => '',
            default => $brand !== '' ? $brand : '',
        };
    }

    /** Filename inside views/img/credit-cards/, or empty if unknown. */
    public static function brandIconFile(string $brand): string
    {
        $key = self::normalizeBrandKey($brand);
        $known = ['visa', 'mastercard', 'amex', 'elo', 'hipercard', 'diners', 'jcb'];

        return in_array($key, $known, true) ? $key . '.svg' : '';
    }
}
