<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Security;

use Cart;
use Customer;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PrestaShop payment checklist: always double-check id_cart ownership
 * before creating an order.
 */
final class CartGuard
{
    /**
     * Front-office: cart must belong to the given customer id (0 = guest/unknown session).
     *
     * @throws \PrestaShopException
     */
    public static function assertPayableCart(
        ?\Cart $cart,
        ?string $secureKeyFromRequest = null,
        int $contextCustomerId = 0,
    ): \Cart {
        $cart = self::assertCartStructure($cart, $secureKeyFromRequest);

        if ($contextCustomerId > 0 && (int) $cart->id_customer !== $contextCustomerId) {
            throw new \PrestaShopException('Cart does not belong to the current customer.');
        }

        return $cart;
    }

    /**
     * Server-to-server (webhook): no browser session — verify cart integrity only.
     *
     * @throws \PrestaShopException
     */
    public static function assertCartForWebhook(int $idCart): \Cart
    {
        $cart = new \Cart($idCart);

        return self::assertCartStructure($cart, null, false);
    }

    /**
     * @throws \PrestaShopException
     */
    private static function assertCartStructure(
        ?\Cart $cart,
        ?string $secureKeyFromRequest,
        bool $mustNotHaveOrder = true,
    ): \Cart {
        if (!$cart instanceof \Cart || !(int) $cart->id) {
            throw new \PrestaShopException('Invalid cart.');
        }

        if (!\Validate::isLoadedObject($cart)) {
            throw new \PrestaShopException('Cart could not be loaded.');
        }

        $expectedKey = (string) $cart->secure_key;
        if ($secureKeyFromRequest !== null && $secureKeyFromRequest !== '') {
            if ($expectedKey === '' || !hash_equals($expectedKey, $secureKeyFromRequest)) {
                throw new \PrestaShopException('Invalid cart secure key.');
            }
        }

        if ($mustNotHaveOrder && $cart->orderExists()) {
            throw new \PrestaShopException('An order already exists for this cart.');
        }

        $customer = new \Customer((int) $cart->id_customer);
        if (!\Validate::isLoadedObject($customer)) {
            throw new \PrestaShopException('Customer not found for cart.');
        }

        if (!(int) $cart->id_address_invoice || !(int) $cart->id_address_delivery) {
            throw new \PrestaShopException('Cart addresses are incomplete.');
        }

        return $cart;
    }

    public static function assertCartIdMatches(\Cart $cart, int $idCart): void
    {
        if ((int) $cart->id !== $idCart) {
            throw new \PrestaShopException('id_cart mismatch.');
        }
    }
}
