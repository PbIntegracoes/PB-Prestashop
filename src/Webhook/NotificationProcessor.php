<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Webhook;

use Order;
use PbConnect\Order\OrderMapper;
use PbConnect\Repository\TransactionRepository;
use PbConnect\Security\CredentialVault;
use PbConnect\Support\GatewayAmount;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class NotificationProcessor
{
    private OrderMapper $orderMapper;
    private TransactionRepository $transactions;
    private \Module $module;

    public function __construct(\Module $module)
    {
        $this->module = $module;
        $this->orderMapper = new OrderMapper();
        $this->transactions = new TransactionRepository();
    }

    /**
     * Webhook keyed by id_cart + HMAC hash (secret derived from Connect Key).
     */
    public function process(int $idCart, string $hash, array $payload): void
    {
        if (!CredentialVault::validateWebhookHash($idCart, $hash)) {
            throw new \InvalidArgumentException('Invalid webhook signature.');
        }

        $cart = new \Cart($idCart);
        if (!\Validate::isLoadedObject($cart)) {
            throw new \InvalidArgumentException('Cart not found.');
        }

        $referenceId = (string) ($payload['reference_id'] ?? '');
        $expectedRef = 'cart-' . $idCart;
        if ($referenceId !== '' && $referenceId !== $expectedRef && $referenceId !== (string) $idCart) {
            // Allow legacy reference = PrestaShop order id if already created
            $orderByRef = $this->findOrderForCart($cart);
            if (!$orderByRef || $referenceId !== (string) $orderByRef->id) {
                throw new \InvalidArgumentException('reference_id mismatch.');
            }
        }

        $status = $this->resolveStatus($payload);
        $amountCents = GatewayAmount::toCents($payload);
        $paymentMethod = $this->resolvePaymentMethod($payload);

        $order = $this->findOrderForCart($cart);
        if (!$order) {
            // Pattern (2): create order only after gateway confirmation (e.g. Checkout PB paid)
            if (!$this->isPaid($status)) {
                return;
            }
            $amountPaid = $amountCents > 0 ? round($amountCents / 100, 2) : 0.0;
            if ($amountPaid <= 0) {
                throw new \InvalidArgumentException('Gateway amount missing.');
            }
            if (!$this->module instanceof \Pbconnect) {
                throw new \RuntimeException('Payment module cannot validate order.');
            }
            $module = $this->module;
            $idOrder = $module->validateOrderFromGateway(
                $cart,
                $this->orderMapper->getPaidOrderStateId(),
                $amountPaid,
                $paymentMethod,
                true
            );
            $order = new \Order($idOrder);
        }

        $this->applyPayloadToOrder($order, $payload, $idCart);
    }

    /**
     * Persist payload + sync order state (webhook and admin force-update).
     *
     * @param array<string, mixed> $payload
     */
    public function applyPayloadToOrder(Order $order, array $payload, ?int $idCart = null): void
    {
        $status = $this->resolveStatus($payload);
        $amountCents = GatewayAmount::toCents($payload);
        $paymentMethod = $this->resolvePaymentMethod($payload);
        $pbOrderId = (string) ($payload['id'] ?? '');
        $cartId = $idCart ?? (int) $order->id_cart;

        $this->transactions->save(
            (int) $order->id,
            $cartId > 0 ? $cartId : null,
            $paymentMethod,
            $status,
            $amountCents,
            $pbOrderId,
            $this->resolveChargeId($payload),
            json_encode($payload)
        );

        if ($this->isPaid($status)) {
            $this->markOrderPaid($order);
        } elseif ($this->isDeclined($status)) {
            // Checkout PB stays OPEN after DECLINED so the buyer can retry on the same
            // link. Persist the attempt, but do not flip the PrestaShop order to error
            // while a chk_ session can still produce a PAID order.
            if (!$this->orderHasCheckoutSession((int) $order->id)) {
                $this->markOrderError($order);
            }
        }
    }

    private function orderHasCheckoutSession(int $idOrder): bool
    {
        foreach ($this->transactions->findByOrder($idOrder) as $row) {
            $id = (string) ($row['pb_order_id'] ?? '');
            if (str_starts_with($id, 'chk_') || str_starts_with($id, 'CHEC')) {
                return true;
            }
        }

        return false;
    }

    private function findOrderForCart(\Cart $cart): ?\Order
    {
        if (!$cart->orderExists()) {
            return null;
        }
        $idOrder = (int) \Order::getIdByCartId((int) $cart->id);
        if ($idOrder <= 0) {
            return null;
        }
        $order = new \Order($idOrder);

        return \Validate::isLoadedObject($order) ? $order : null;
    }

    private function resolveStatus(array $payload): string
    {
        if (!empty($payload['status'])) {
            return (string) $payload['status'];
        }
        if (!empty($payload['charges'][0]['status'])) {
            return (string) $payload['charges'][0]['status'];
        }
        if (!empty($payload['qr_codes'][0]['status'])) {
            return (string) $payload['qr_codes'][0]['status'];
        }

        return 'UNKNOWN';
    }

    private function resolvePaymentMethod(array $payload): string
    {
        if (!empty($payload['charges'][0]['payment_method']['type'])) {
            return (string) $payload['charges'][0]['payment_method']['type'];
        }
        if (!empty($payload['qr_codes'])) {
            return 'PIX';
        }

        return 'UNKNOWN';
    }

    private function resolveChargeId(array $payload): ?string
    {
        return isset($payload['charges'][0]['id']) ? (string) $payload['charges'][0]['id'] : null;
    }

    private function isPaid(string $status): bool
    {
        return in_array(strtoupper($status), ['PAID', 'AVAILABLE'], true);
    }

    private function isDeclined(string $status): bool
    {
        return in_array(strtoupper($status), ['DECLINED', 'CANCELED', 'FAILED'], true);
    }

    private function markOrderPaid(\Order $order): void
    {
        $paidState = $this->orderMapper->getPaidOrderStateId();
        if ((int) $order->getCurrentState() === $paidState) {
            return;
        }

        $history = new \OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($paidState, $order);
        $history->addWithemail();
    }

    private function markOrderError(\Order $order): void
    {
        $errorState = $this->orderMapper->getErrorOrderStateId();
        if ((int) $order->getCurrentState() === $errorState) {
            return;
        }

        $history = new \OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($errorState, $order);
        $history->addWithemail();
    }
}
