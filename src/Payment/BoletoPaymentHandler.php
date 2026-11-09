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

use PbConnect\Api\PbClient;
use PbConnect\Config\ConfigurationKeys;
use PbConnect\Order\OrderMapper;
use PbConnect\Repository\TransactionRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class BoletoPaymentHandler
{
    private PbClient $client;
    private OrderMapper $mapper;
    private PaymentUrlBuilder $urls;
    private TransactionRepository $transactions;

    public function __construct(\Pbconnect $module, PbClient $client)
    {
        $this->client = $client;
        $this->mapper = new OrderMapper();
        $this->urls = $module->urls();
        $this->transactions = new TransactionRepository();
    }

    public function createAtGateway(\Cart $cart): array
    {
        $idCart = (int) $cart->id;
        $referenceId = 'cart-' . $idCart;
        $body = $this->mapper->buildFromCart($cart, $referenceId);
        $days = (int) \Configuration::get(ConfigurationKeys::BOLETO_EXPIRY_DAYS) ?: 7;
        $amountCents = $this->mapper->cartTotalCents($cart);
        $discount = (float) \Configuration::get(ConfigurationKeys::BOLETO_DISCOUNT);
        if ($discount > 0) {
            $amountCents = (int) round($amountCents * (1 - $discount / 100));
        }

        $body['notification_urls'] = [$this->urls->notificationUrlForCart($idCart)];
        $body['charges'] = [[
            'reference_id' => 'boleto-cart-' . $idCart,
            'description' => 'Cart #' . $idCart,
            'amount' => ['value' => $amountCents, 'currency' => 'BRL'],
            'payment_method' => [
                'type' => 'BOLETO',
                'boleto' => [
                    'due_date' => gmdate('Y-m-d', time() + $days * 86400),
                ],
            ],
        ]];

        return $this->client->createOrder($body);
    }

    public function persistTransaction(int $idOrder, int $idCart, array $response): void
    {
        $amountCents = (int) ($response['charges'][0]['amount']['value'] ?? 0);
        $this->transactions->save(
            $idOrder,
            $idCart,
            'BOLETO',
            (string) ($response['status'] ?? $response['charges'][0]['status'] ?? 'WAITING'),
            $amountCents,
            (string) ($response['id'] ?? ''),
            (string) ($response['charges'][0]['id'] ?? ''),
            json_encode($response)
        );
    }

    /**
     * @return array{hosted_url: string, barcode: string, digitable_line: string}
     */
    public static function extractBoletoDisplay(array $response): array
    {
        $boleto = $response['charges'][0]['payment_method']['boleto'] ?? [];

        return [
            'hosted_url' => (string) ($boleto['hosted_url'] ?? $boleto['url'] ?? ''),
            'barcode' => (string) ($boleto['barcode'] ?? ''),
            'digitable_line' => (string) ($boleto['formatted_barcode'] ?? $boleto['digitable_line'] ?? $boleto['barcode'] ?? ''),
        ];
    }
}
