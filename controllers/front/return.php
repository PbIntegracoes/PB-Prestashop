<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PbConnect\Security\CartGuard;
use PbConnect\Security\PaymentRequestToken;

/**
 * Return URL after Checkout PB. Verifies cart token; redirects to confirmation if order exists.
 */
class PbconnectReturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        $idCart = (int) Tools::getValue('id_cart');
        $token = (string) Tools::getValue('token');
        $key = (string) Tools::getValue('key');

        try {
            $shopId = (int) $this->context->shop->id;
            if (!PaymentRequestToken::validate($idCart, $token, $shopId)) {
                throw new PrestaShopException('Invalid return token.');
            }
            $cart = new Cart($idCart);
            CartGuard::assertCartIdMatches($cart, $idCart);
            if ($key !== '' && !hash_equals((string) $cart->secure_key, $key)) {
                throw new PrestaShopException('Invalid cart key.');
            }

            /** @var Pbconnect $module */
            $module = $this->module;

            // Waiting order is created before redirect to Checkout PB; confirmation is the thank-you page.
            // Payment status is updated later by webhook (production) or admin "Forçar atualização".
            if ($cart->orderExists()) {
                $idOrder = (int) Order::getIdByCartId($idCart);
                $url = $module->urls()->orderConfirmationUrl(
                    $idCart,
                    (int) $module->id,
                    $idOrder,
                    (string) $cart->secure_key
                );
                Tools::redirect($url);
            }

            // Legacy carts (pre-waiting-order flow): keep soft pending page.
            $this->context->smarty->assign([
                'pb_return_pending' => true,
                'pb_id_cart' => $idCart,
            ]);
            $this->setTemplate('module:pbconnect/views/templates/front/return_pending.tpl');
        } catch (Throwable $e) {
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
        }
    }
}
