<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class TransactionRepository
{
    public function save(
        int $idOrder,
        ?int $idCart,
        string $paymentMethod,
        string $status,
        int $amountCents,
        ?string $pbOrderId = null,
        ?string $pbChargeId = null,
        ?string $payload = null,
    ): bool {
        $now = date('Y-m-d H:i:s');

        return (bool) \Db::getInstance()->insert('pb_transaction', [
            'id_order' => $idOrder,
            'id_cart' => $idCart,
            'pb_order_id' => pSQL($pbOrderId ?? ''),
            'pb_charge_id' => pSQL($pbChargeId ?? ''),
            'payment_method' => pSQL($paymentMethod),
            'status' => pSQL($status),
            'amount_cents' => $amountCents,
            'payload' => pSQL($payload ?? '', true),
            'date_add' => $now,
            'date_upd' => $now,
        ]);
    }

    public function findByOrder(int $idOrder): array
    {
        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('pb_transaction');
        $sql->where('id_order = ' . (int) $idOrder);
        $sql->orderBy('date_add DESC');

        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    public function findLatestByPbOrderId(string $pbOrderId): ?array
    {
        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('pb_transaction');
        $sql->where("pb_order_id = '" . pSQL($pbOrderId) . "'");
        $sql->orderBy('date_add DESC');

        $row = \Db::getInstance()->getRow($sql);

        return is_array($row) ? $row : null;
    }

    public function findLatestByCart(int $idCart): ?array
    {
        if ($idCart <= 0) {
            return null;
        }
        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('pb_transaction');
        $sql->where('id_cart = ' . (int) $idCart);
        $sql->orderBy('date_add DESC');

        $row = \Db::getInstance()->getRow($sql);

        return is_array($row) ? $row : null;
    }

    public function getLatestPayload(int $idOrder): ?array
    {
        $rows = $this->findByOrder($idOrder);
        if ($rows === []) {
            return null;
        }

        // Prefer a terminal paid payload when present (charge_created often arrives after
        // bill_paid and would otherwise hide QR / card details from the "latest" row).
        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['status'] ?? ''));
            if (in_array($status, ['PAID', 'AVAILABLE'], true)) {
                $decoded = $this->decodePayloadRow($row);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        return $this->decodePayloadRow($rows[0]);
    }

    /**
     * PIX QR lives on the create/WAITING response. Later webhooks (charge_created,
     * bill_paid) are slim and omit qr_codes — keep a dedicated lookup for display/proxy.
     *
     * @return array<string, mixed>|null
     */
    public function findPixPayloadForOrder(int $idOrder): ?array
    {
        foreach ($this->findByOrder($idOrder) as $row) {
            $decoded = $this->decodePayloadRow($row);
            if ($decoded === null) {
                continue;
            }
            $qr = $decoded['qr_codes'][0] ?? null;
            if (!is_array($qr)) {
                continue;
            }
            $text = (string) ($qr['text'] ?? $qr['emv'] ?? '');
            $hasLink = false;
            foreach ($qr['links'] ?? [] as $link) {
                if (!empty($link['href'])) {
                    $hasLink = true;
                    break;
                }
            }
            if ($text !== '' || $hasLink) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    private function decodePayloadRow(array $row): ?array
    {
        $raw = (string) ($row['payload'] ?? '');
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
