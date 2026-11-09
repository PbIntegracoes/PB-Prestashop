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

use PbConnect\Config\ConfigurationKeys;
use PbConnect\Security\CredentialVault;
use PbConnect\Security\PaymentRequestToken;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentUrlBuilder
{
    private string $moduleName;

    private \Link $link;

    private int $shopId;

    public function __construct(string $moduleName, \Link $link, int $shopId)
    {
        $this->moduleName = $moduleName;
        $this->link = $link;
        $this->shopId = $shopId;
    }

    /**
     * Notification URL keyed by id_cart (max 100 chars). Hash is HMAC, not public knowledge.
     */
    public function notificationUrlForCart(int $idCart): string
    {
        $hash = CredentialVault::webhookHash($idCart);

        $url = $this->link->getModuleLink(
            $this->moduleName,
            'notif',
            ['id_cart' => $idCart, 'h' => $hash],
            true
        );

        if (strlen($url) <= 100) {
            return $url;
        }

        $short = rtrim($this->link->getBaseLink(), '/') . '/pbc/n/' . $idCart . '/' . $hash;

        if (strlen($short) <= 100 || \Configuration::get(ConfigurationKeys::USE_PROXY) === '1') {
            return $short;
        }

        return $url;
    }

    public function threeDsReturnUrl(\Cart $cart): string
    {
        $idCart = (int) $cart->id;

        // Force absolute HTTPS when the shop supports it (Cardinal/Vindi prefer https return_url).
        return $this->link->getModuleLink(
            $this->moduleName,
            'threedsreturn',
            [
                'id_cart' => $idCart,
                'token' => PaymentRequestToken::forCart($idCart, $this->shopId),
            ],
            true
        );
    }

    public function pixQrProxyUrl(int $idCart): string
    {
        $hash = CredentialVault::webhookHash($idCart);

        return $this->link->getModuleLink(
            $this->moduleName,
            'process',
            [
                'action' => 'pix_qr',
                'id_cart' => $idCart,
                'h' => $hash,
                'token' => PaymentRequestToken::forCart($idCart, $this->shopId),
            ],
            true
        );
    }

    public function orderConfirmationUrl(int $idCart, int $idModule, int $idOrder, string $secureKey): string
    {
        return $this->link->getPageLink('order-confirmation', true, null, [
            'id_cart' => $idCart,
            'id_module' => $idModule,
            'id_order' => $idOrder,
            'key' => $secureKey,
        ]);
    }

    public function validationUrl(string $method, \Cart $cart): string
    {
        $idCart = (int) $cart->id;

        return $this->link->getModuleLink(
            $this->moduleName,
            'validation',
            [
                'method' => $method,
                'id_cart' => $idCart,
                'key' => (string) $cart->secure_key,
                'token' => PaymentRequestToken::forCart($idCart, $this->shopId),
            ],
            true
        );
    }

    public function checkoutReturnUrl(\Cart $cart): string
    {
        $idCart = (int) $cart->id;

        return $this->link->getModuleLink(
            $this->moduleName,
            'return',
            [
                'id_cart' => $idCart,
                'key' => (string) $cart->secure_key,
                'token' => PaymentRequestToken::forCart($idCart, $this->shopId),
            ],
            true
        );
    }
}
