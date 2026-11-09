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

use PbConnect\Order\OrderMapper;
use PbConnect\Payment\BoletoPaymentHandler;
use PbConnect\Payment\CardPaymentHandler;
use PbConnect\Payment\CheckoutPbPaymentHandler;
use PbConnect\Payment\PixPaymentHandler;
use PbConnect\Security\CartGuard;
use PbConnect\Security\PaymentRequestToken;
use PbConnect\Support\GatewayAmount;
use PbConnect\Support\ShopMessages;

/**
 * Payment validation — PrestaShop checklist:
 * - verify id_cart ownership + token
 * - call gateway first
 * - validateOrder() only after gateway acceptance, using gateway amount
 */
class PbconnectValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess(): void
    {
        $this->processPayment();
    }

    public function initContent(): void
    {
        parent::initContent();
    }

    private function processPayment(): void
    {
        /** @var Pbconnect $module */
        $module = $this->module;
        $method = (string) Tools::getValue('method', 'pix');
        $idCartRequest = (int) Tools::getValue('id_cart');
        $token = (string) Tools::getValue('token');
        $secureKey = (string) Tools::getValue('key');

        try {
            $shopId = (int) $this->context->shop->id;
            $cart = CartGuard::assertPayableCart(
                $this->context->cart,
                $secureKey !== '' ? $secureKey : null,
                (int) ($this->context->customer->id ?? 0)
            );
            if ($idCartRequest > 0) {
                CartGuard::assertCartIdMatches($cart, $idCartRequest);
            }
            if (!PaymentRequestToken::validate((int) $cart->id, $token, $shopId)) {
                throw new PrestaShopException('Invalid payment token.');
            }

            $client = $module->getClient();
            $urls = $module->urls();
            $mapper = new OrderMapper();
            $invoiceAddress = new Address((int) $cart->id_address_invoice);
            $taxId = $mapper->getTaxId($this->context->customer, $invoiceAddress);
            if ($mapper->isPlaceholderTaxId($taxId) || !$mapper->looksLikeTaxId($taxId)) {
                throw new PrestaShopException(ShopMessages::invalidTaxIdMissing($module));
            }

            switch ($method) {
                case 'pix':
                    $handler = new PixPaymentHandler($module, $client);
                    $response = $handler->createAtGateway($cart);
                    $amountPaid = GatewayAmount::toFloat($response);
                    if ($amountPaid <= 0) {
                        throw new PrestaShopException('Gateway did not return a valid amount.');
                    }
                    $idOrder = $module->validateOrderFromGateway(
                        $cart,
                        $mapper->getWaitingOrderStateId(),
                        $amountPaid,
                        'PIX'
                    );
                    $handler->persistTransaction($idOrder, (int) $cart->id, $response);
                    // Order confirmation shows PIX QR via hookPaymentReturn (cart is already consumed).
                    Tools::redirect($urls->orderConfirmationUrl(
                        (int) $cart->id,
                        (int) $module->id,
                        $idOrder,
                        (string) $cart->secure_key
                    ));

                    return;

                case 'boleto':
                    $handler = new BoletoPaymentHandler($module, $client);
                    $response = $handler->createAtGateway($cart);
                    $amountPaid = GatewayAmount::toFloat($response);
                    if ($amountPaid <= 0) {
                        throw new PrestaShopException('Gateway did not return a valid amount.');
                    }
                    $idOrder = $module->validateOrderFromGateway(
                        $cart,
                        $mapper->getWaitingOrderStateId(),
                        $amountPaid,
                        'BOLETO'
                    );
                    $handler->persistTransaction($idOrder, (int) $cart->id, $response);
                    $boleto = BoletoPaymentHandler::extractBoletoDisplay($response);
                    if ($boleto['hosted_url'] !== '') {
                        Tools::redirect($boleto['hosted_url']);
                    }
                    Tools::redirect($urls->orderConfirmationUrl(
                        (int) $cart->id,
                        (int) $module->id,
                        $idOrder,
                        (string) $cart->secure_key
                    ));

                    return;

                case 'checkout_pb':
                    // Pattern (1): create waiting order, then redirect to hosted Checkout PB.
                    // Webhook / admin "Forçar atualização" mark it paid later (localhost never receives notifs).
                    $handler = new CheckoutPbPaymentHandler($module, $client);
                    $response = $handler->createAtGateway($cart);
                    if (empty($response['pay_link'])) {
                        throw new PrestaShopException('Checkout PB URL was not returned.');
                    }
                    $amountPaid = GatewayAmount::toFloat($response);
                    if ($amountPaid <= 0) {
                        $amountPaid = (float) $cart->getOrderTotal(true, Cart::BOTH);
                    }
                    if ($amountPaid <= 0) {
                        throw new PrestaShopException('Gateway did not return a valid amount.');
                    }
                    $idOrder = $module->validateOrderFromGateway(
                        $cart,
                        $mapper->getWaitingOrderStateId(),
                        $amountPaid,
                        'CHECKOUT_PB'
                    );
                    $handler->persistTransaction($idOrder, (int) $cart->id, $response);
                    Tools::redirect($response['pay_link']);

                    return;

                case 'card':
                    $gatewayToken = (string) Tools::getValue('pb_gateway_token');
                    $installments = max(1, (int) Tools::getValue('pb_installments', 1));
                    $amountCents = (int) Tools::getValue('pb_amount_cents', 0);
                    $company = (string) Tools::getValue('pb_payment_company_code', 'mastercard');
                    $profileId = (int) Tools::getValue('pb_payment_profile_id', 0);
                    $cardHolder = trim((string) Tools::getValue('pb_card_holder', ''));
                    $cardExpiry = trim((string) Tools::getValue('pb_card_expiry', ''));
                    $cardLast4 = preg_replace('/\D+/', '', (string) Tools::getValue('pb_card_last4', '')) ?: '';
                    if (strlen($cardLast4) > 4) {
                        $cardLast4 = substr($cardLast4, -4);
                    }
                    $threeDsRaw = Tools::getValue('pb_three_ds');
                    $threeDs = null;
                    if (is_string($threeDsRaw) && $threeDsRaw !== '') {
                        $decoded = json_decode($threeDsRaw, true);
                        if (is_array($decoded)) {
                            $threeDs = $decoded;
                        }
                    }

                    if ($threeDs === null && $gatewayToken === '') {
                        throw new PrestaShopException('Card token is missing.');
                    }

                    $handler = new CardPaymentHandler($module, $client);
                    $response = $handler->chargeAtGateway($cart, [
                        'gateway_token' => $gatewayToken,
                        'installments' => $installments,
                        'amount_cents' => $amountCents,
                        'payment_company_code' => $company,
                        'payment_profile_id' => $profileId,
                        'three_ds' => $threeDs,
                    ]);

                    $status = strtoupper((string) ($response['status'] ?? $response['charges'][0]['status'] ?? ''));
                    $amountPaid = GatewayAmount::toFloat($response);
                    if ($amountPaid <= 0) {
                        throw new PrestaShopException('Gateway did not return a valid amount.');
                    }

                    if (!in_array($status, ['PAID', 'AVAILABLE'], true)) {
                        throw new PrestaShopException(ShopMessages::cardNotApproved($module, $response));
                    }

                    $view = \PbConnect\Admin\OrderPaymentView::fromPayload($response);
                    $methodLabel = 'CREDIT_CARD';
                    if (!empty($view['method'])) {
                        $methodLabel = (string) $view['method'];
                    }
                    if ($installments > 1) {
                        $methodLabel .= ' ' . $installments . 'x';
                    }
                    $txId = \PbConnect\Admin\OrderPaymentView::preferredTransactionId($view);

                    $idOrder = $module->validateOrderFromGateway(
                        $cart,
                        $mapper->getPaidOrderStateId(),
                        $amountPaid,
                        $methodLabel,
                        false,
                        $txId !== '' ? ['transaction_id' => $txId] : []
                    );
                    $handler->persistTransaction($idOrder, (int) $cart->id, $response);
                    \PbConnect\Payment\OrderPaymentSync::syncFromPayload($idOrder, $response, [
                        'card_holder' => $cardHolder,
                        'card_expiration' => $cardExpiry,
                        'card_last' => $cardLast4,
                        'card_brand' => $company,
                        'installments' => $installments,
                    ]);
                    Tools::redirect($urls->orderConfirmationUrl(
                        (int) $cart->id,
                        (int) $module->id,
                        $idOrder,
                        (string) $cart->secure_key
                    ));

                    return;

                default:
                    throw new PrestaShopException('Unknown payment method.');
            }
        } catch (Throwable $e) {
            $this->errors[] = ShopMessages::fromException($module, $e);
        }

        $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
    }
}
