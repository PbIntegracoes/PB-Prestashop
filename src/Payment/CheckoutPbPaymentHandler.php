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
use PbConnect\Support\GatewayAmount;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CheckoutPbPaymentHandler
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
     * Create hosted checkout at the gateway BEFORE validateOrder().
     */
    public function createAtGateway(\Cart $cart): array
    {
        $idCart = (int) $cart->id;
        $customer = new \Customer((int) $cart->id_customer);
        $address = new \Address((int) $cart->id_address_invoice);
        $shippingAddress = new \Address((int) $cart->id_address_delivery);

        $base = $this->mapper->buildFromCart($cart, 'cart-' . $idCart);
        $methodsRaw = (string) \Configuration::get(ConfigurationKeys::CHECKOUT_PB_METHODS);
        $methods = [];
        foreach (array_filter(array_map('trim', explode(',', $methodsRaw))) as $type) {
            $methods[] = ['type' => strtoupper($type)];
        }
        if ($methods === []) {
            $methods = [
                ['type' => 'CREDIT_CARD'],
                ['type' => 'PIX'],
                ['type' => 'BOLETO'],
            ];
        }

        $shippingCents = $this->mapper->cartShippingCents($cart);
        $shipping = [
            'type' => $shippingCents > 0 ? 'FIXED' : 'FREE',
            'amount' => $shippingCents,
            'address_modifiable' => false,
            'address' => $this->mapper->buildCustomerAddress($shippingAddress),
        ];

        $customerPayload = $base['customer'];
        $customerPayload['address'] = $this->mapper->buildCustomerAddress($address);

        $body = [
            'reference_id' => 'cart-' . $idCart,
            // Allow the buyer to fix phone/email/tax_id on the hosted page (same as Woo / API examples).
            'customer_modifiable' => true,
            'customer' => $customerPayload,
            'items' => $base['items'],
            'shipping' => $shipping,
            'payment_methods' => $methods,
            'notification_urls' => [$this->urls->notificationUrlForCart($idCart)],
            'return_url' => $this->urls->checkoutReturnUrl($cart),
            'reusable' => false,
        ];

        $response = $this->client->createCheckout($body);
        $payUrl = (string) ($response['url'] ?? '');
        if ($payUrl === '') {
            foreach ($response['links'] ?? [] as $link) {
                if (strtoupper((string) ($link['rel'] ?? '')) === 'PAY') {
                    $payUrl = (string) ($link['href'] ?? '');
                    break;
                }
            }
        }

        $response['pay_link'] = $payUrl;

        return $response;
    }

    public function persistTransaction(int $idOrder, int $idCart, array $response): void
    {
        $this->transactions->save(
            $idOrder,
            $idCart,
            'CHECKOUT_PB',
            (string) ($response['status'] ?? 'OPEN'),
            GatewayAmount::toCents($response),
            (string) ($response['id'] ?? ''),
            null,
            json_encode($response)
        );
    }
}
