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

use Order;
use PbConnect\Api\PbClient;
use PbConnect\Payment\OrderPaymentSync;
use PbConnect\Repository\TransactionRepository;
use PbConnect\Webhook\NotificationProcessor;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Admin "Forçar atualização" — GET order from API and apply like a webhook.
 */
final class ForceOrderUpdateHandler
{
    /**
     * @return array{ok: bool, changed: bool, message: string}
     */
    public function run(\Pbconnect $module, int $idOrder, string $pbOrderId): array
    {
        $order = new Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return ['ok' => false, 'changed' => false, 'message' => 'Pedido PrestaShop não encontrado.'];
        }

        $repo = new TransactionRepository();
        $rows = $repo->findByOrder($idOrder);
        if ($rows === []) {
            return ['ok' => false, 'changed' => false, 'message' => 'Nenhuma transação PB Connect neste pedido.'];
        }

        $knownIds = [];
        foreach ($rows as $row) {
            foreach (['pb_order_id', 'pb_charge_id'] as $key) {
                $v = trim((string) ($row[$key] ?? ''));
                if ($v !== '') {
                    $knownIds[$v] = true;
                }
            }
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            if (is_array($payload) && !empty($payload['id'])) {
                $knownIds[(string) $payload['id']] = true;
            }
        }

        // Always prefer the checkout session when present — retries share the same chk_
        // and order_ids grows with DECLINED + PAID attempts.
        $checkoutId = self::findCheckoutSessionId($rows);
        if ($checkoutId !== '') {
            $pbOrderId = $checkoutId;
        }

        if ($pbOrderId === '' || !isset($knownIds[$pbOrderId])) {
            return ['ok' => false, 'changed' => false, 'message' => 'ID do pedido PB não confere com este pedido.'];
        }

        $client = $module->getClient();
        try {
            $fresh = $this->fetchPayload($client, $pbOrderId);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'changed' => false,
                'message' => 'Erro ao consultar pedido: ' . $e->getMessage(),
            ];
        }

        if ($fresh === [] || empty($fresh['id'])) {
            return [
                'ok' => true,
                'changed' => false,
                'message' => 'Consulta OK, mas ainda sem pedido pago vinculado (ex.: checkout aberto).',
            ];
        }

        $prev = $repo->getLatestPayload($idOrder);
        $prevMd5 = is_array($prev) ? md5(serialize($prev)) : '';
        $nextMd5 = md5(serialize($fresh));
        if ($prevMd5 !== '' && $prevMd5 === $nextMd5) {
            return [
                'ok' => true,
                'changed' => false,
                'message' => 'Pedido atualizado manualmente, mas nada mudou desde o último update.',
            ];
        }

        (new NotificationProcessor($module))->applyPayloadToOrder($order, $fresh);
        OrderPaymentSync::syncFromPayload($idOrder, $fresh);

        $status = strtoupper((string) ($fresh['status'] ?? $fresh['charges'][0]['status'] ?? ''));

        return [
            'ok' => true,
            'changed' => true,
            'message' => 'Pedido atualizado manualmente'
                . ($status !== '' ? ' (' . $status . ').' : '.'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function findCheckoutSessionId(array $rows): string
    {
        foreach ($rows as $row) {
            $id = trim((string) ($row['pb_order_id'] ?? ''));
            if (self::isCheckoutSessionId($id)) {
                return $id;
            }
        }

        return '';
    }

    public static function isCheckoutSessionId(string $id): bool
    {
        return str_starts_with($id, 'chk_') || str_starts_with($id, 'CHEC');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPayload(PbClient $client, string $id): array
    {
        if (self::isCheckoutSessionId($id)) {
            return $this->resolveBestOrderFromCheckout($client, $client->getCheckout($id));
        }

        return $client->getOrder($id);
    }

    /**
     * Checkout PB keeps the session OPEN after DECLINED so the buyer can retry.
     * order_ids is append-only: [declined…, paid]. Prefer PAID / AVAILABLE.
     *
     * @param array<string, mixed> $checkout
     *
     * @return array<string, mixed>
     */
    private function resolveBestOrderFromCheckout(PbClient $client, array $checkout): array
    {
        $orderIds = [];
        $rawIds = $checkout['order_ids'] ?? [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $oid) {
                if (is_string($oid) && $oid !== '') {
                    $orderIds[] = $oid;
                } elseif (is_scalar($oid) && (string) $oid !== '') {
                    $orderIds[] = (string) $oid;
                }
            }
        }
        $nested = $checkout['orders'] ?? [];
        if (is_array($nested)) {
            foreach ($nested as $ord) {
                if (is_array($ord) && !empty($ord['id'])) {
                    $orderIds[] = (string) $ord['id'];
                }
            }
        }

        $last = is_array($checkout['last_result']['order'] ?? null)
            ? $checkout['last_result']['order']
            : null;
        if (is_array($last) && !empty($last['id'])) {
            $lastId = (string) $last['id'];
            $lastStatus = strtoupper((string) ($last['status'] ?? ''));
            if ($this->isPaidStatus($lastStatus)) {
                return $client->getOrder($lastId);
            }
            $orderIds[] = $lastId;
        }

        $orderIds = array_values(array_unique($orderIds));
        if ($orderIds === []) {
            return [];
        }

        // Newest first (retries are appended).
        $fallback = [];
        foreach (array_reverse($orderIds) as $oid) {
            $order = $client->getOrder($oid);
            $status = strtoupper((string) (
                $order['status']
                ?? ($order['charges'][0]['status'] ?? '')
            ));
            if ($this->isPaidStatus($status)) {
                return $order;
            }
            if ($fallback === []) {
                $fallback = $order;
            }
        }

        // No paid attempt yet — return newest (may be DECLINED / WAITING).
        return $fallback;
    }

    private function isPaidStatus(string $status): bool
    {
        return in_array(strtoupper($status), ['PAID', 'AVAILABLE', 'AUTHORIZED'], true);
    }
}
