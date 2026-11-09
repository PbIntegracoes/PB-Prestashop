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

final class CardPaymentHandler
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

    /**
     * Charge at the gateway BEFORE validateOrder(). Pattern (2) in PrestaShop checklist.
     *
     * @param array{
     *   gateway_token?: string,
     *   installments?: int,
     *   amount_cents?: int,
     *   payment_company_code?: string,
     *   payment_profile_id?: int,
     *   three_ds?: array<string, mixed>|null
     * } $cardData
     */
    public function chargeAtGateway(\Cart $cart, array $cardData): array
    {
        $idCart = (int) $cart->id;
        $referenceId = 'cart-' . $idCart;
        $body = $this->mapper->buildFromCart($cart, $referenceId);

        $amountCents = (int) ($cardData['amount_cents'] ?? 0);
        if ($amountCents < 100) {
            $amountCents = $this->mapper->cartTotalCents($cart);
        }
        $installments = max(1, (int) ($cardData['installments'] ?? 1));

        $paymentMethod = [
            'type' => 'CREDIT_CARD',
            'installments' => $installments,
        ];

        $threeDs = $cardData['three_ds'] ?? null;
        $profileId = (int) ($cardData['payment_profile_id'] ?? 0);
        if (is_array($threeDs) && $profileId > 0) {
            $paymentMethod['type'] = 'CREDIT_CARD_3DS';
            $paymentMethod['payment_profile_id'] = $profileId;
            $paymentMethod['three_ds'] = $threeDs;
        } else {
            $token = (string) ($cardData['gateway_token'] ?? '');
            if ($token === '') {
                throw new \InvalidArgumentException('gateway_token is required.');
            }
            $paymentMethod['card'] = ['gateway_token' => $token];
        }

        $body['notification_urls'] = [$this->urls->notificationUrlForCart($idCart)];
        $body['charges'] = [[
            'reference_id' => 'cc-cart-' . $idCart,
            'description' => 'Cart #' . $idCart,
            'amount' => ['value' => $amountCents, 'currency' => 'BRL'],
            'payment_method' => $paymentMethod,
        ]];

        return $this->client->createOrder($body);
    }

    public function persistTransaction(int $idOrder, int $idCart, array $response): void
    {
        $status = strtoupper((string) ($response['status'] ?? $response['charges'][0]['status'] ?? 'UNKNOWN'));
        $amountCents = (int) ($response['charges'][0]['amount']['value'] ?? 0);
        $type = (string) ($response['charges'][0]['payment_method']['type'] ?? 'CREDIT_CARD');

        $this->transactions->save(
            $idOrder,
            $idCart,
            $type,
            $status,
            $amountCents,
            (string) ($response['id'] ?? ''),
            (string) ($response['charges'][0]['id'] ?? ''),
            json_encode($response)
        );
    }

    public static function isThreeDsConfigured(): bool
    {
        return \Configuration::get(ConfigurationKeys::CC_3DS_ENABLED) === '1';
    }
}
