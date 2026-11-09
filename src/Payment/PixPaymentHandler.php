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

final class PixPaymentHandler
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
     * Call the gateway BEFORE PrestaShop validateOrder().
     */
    public function createAtGateway(\Cart $cart): array
    {
        $idCart = (int) $cart->id;
        $referenceId = 'cart-' . $idCart;
        $body = $this->mapper->buildFromCart($cart, $referenceId);
        $minutes = (int) \Configuration::get(ConfigurationKeys::PIX_EXPIRY_MINUTES) ?: 1440;
        $amountCents = $this->mapper->cartTotalCents($cart);
        $discount = (float) \Configuration::get(ConfigurationKeys::PIX_DISCOUNT);
        if ($discount > 0) {
            $amountCents = (int) round($amountCents * (1 - $discount / 100));
        }

        $qr = [
            'amount' => ['value' => $amountCents, 'currency' => 'BRL'],
        ];
        if ($minutes > 0) {
            $qr['expiration_date'] = gmdate('Y-m-d\TH:i:s+00:00', time() + $minutes * 60);
        }

        $body['notification_urls'] = [$this->urls->notificationUrlForCart($idCart)];
        $body['qr_codes'] = [$qr];

        return $this->client->createOrder($body);
    }

    public function persistTransaction(int $idOrder, int $idCart, array $response): void
    {
        $amountCents = (int) ($response['qr_codes'][0]['amount']['value'] ?? 0);
        $this->transactions->save(
            $idOrder,
            $idCart,
            'PIX',
            (string) ($response['status'] ?? $response['qr_codes'][0]['status'] ?? 'WAITING'),
            $amountCents,
            (string) ($response['id'] ?? ''),
            null,
            json_encode($response)
        );
    }

    /**
     * @return array{text: string, image_url: string, status: string}
     */
    public static function extractQrDisplay(array $response): array
    {
        $qr = $response['qr_codes'][0] ?? [];
        $text = (string) ($qr['text'] ?? $qr['emv'] ?? '');
        $imageUrl = '';

        foreach ($qr['links'] ?? [] as $link) {
            $rel = strtoupper((string) ($link['rel'] ?? ''));
            if ($rel === 'QRCODE.PNG' || $rel === 'QRCODE.SVG' || str_contains($rel, 'QRCODE')) {
                $imageUrl = (string) ($link['href'] ?? '');
                if ($imageUrl !== '') {
                    break;
                }
            }
        }

        return [
            'text' => $text,
            'image_url' => $imageUrl,
            'status' => (string) ($qr['status'] ?? $response['status'] ?? 'WAITING'),
        ];
    }
}
