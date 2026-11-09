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

use PbConnect\Admin\OrderPaymentView;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Fills native PrestaShop order_payment rows (admin "Pagamento" block).
 */
final class OrderPaymentSync
{
    /**
     * @param array<string, mixed> $payload API order JSON
     * @param array{
     *   card_holder?: string,
     *   card_expiration?: string,
     *   card_last?: string,
     *   card_brand?: string,
     *   installments?: int
     * } $fallback Form/checkout hints when API omits fields
     */
    public static function syncFromPayload(int $idOrder, array $payload, array $fallback = []): void
    {
        $order = new \Order($idOrder);
        if ((int) $order->id <= 0) {
            return;
        }

        $view = OrderPaymentView::fromPayload($payload);
        if (($view['card_holder'] === '') && !empty($fallback['card_holder'])) {
            $view['card_holder'] = (string) $fallback['card_holder'];
        }
        if (($view['card_expiration'] === '') && !empty($fallback['card_expiration'])) {
            $view['card_expiration'] = (string) $fallback['card_expiration'];
        }
        if (($view['card_last'] === '') && !empty($fallback['card_last'])) {
            $view['card_last'] = (string) $fallback['card_last'];
        }
        if (($view['card_brand'] === '') && !empty($fallback['card_brand'])) {
            $view['card_brand'] = (string) $fallback['card_brand'];
        }
        if ($view['installments'] < 1 && !empty($fallback['installments'])) {
            $view['installments'] = max(1, (int) $fallback['installments']);
        }

        $payments = $order->getOrderPayments();
        if ($payments instanceof \PrestaShopCollection) {
            $payments = $payments->getResults();
        }
        if (!is_array($payments) || $payments === []) {
            return;
        }

        /** @var \OrderPayment $payment */
        $payment = $payments[0];
        if (!$payment instanceof \OrderPayment) {
            return;
        }
        $tx = OrderPaymentView::preferredTransactionId($view);
        if ($tx !== '' && (string) $payment->transaction_id === '') {
            $payment->transaction_id = $tx;
        }

        if ($view['is_card'] || $view['card_last'] !== '') {
            if ($view['card_last'] !== '') {
                $first = $view['card_first'] !== '' ? $view['card_first'] : '******';
                $payment->card_number = $first . '******' . $view['card_last'];
            }
            if ($view['card_brand'] !== '') {
                $payment->card_brand = $view['card_brand'];
            }
            if ($view['card_expiration'] !== '') {
                $payment->card_expiration = $view['card_expiration'];
            }
            if ($view['card_holder'] !== '') {
                $payment->card_holder = $view['card_holder'];
            }
        }

        $method = (string) $payment->payment_method;
        if ($view['installments'] > 1 && stripos($method, (string) $view['installments'] . 'x') === false) {
            $payment->payment_method = trim($method . ' ' . $view['installments'] . 'x');
        }

        $payment->update();
    }
}
