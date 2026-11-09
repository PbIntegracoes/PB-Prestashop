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
        $raw = (string) ($rows[0]['payload'] ?? '');
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
