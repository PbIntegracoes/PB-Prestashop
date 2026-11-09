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

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/autoload.php';
}

use PbConnect\Admin\ConfigFormPresenter;
use PbConnect\Api\PbClient;
use PbConnect\Config\ConfigurationKeys;
use PbConnect\Install\Installer;
use PbConnect\Order\OrderMapper;
use PbConnect\Payment\PaymentUrlBuilder;
use PbConnect\Repository\TransactionRepository;
use PbConnect\Security\CartGuard;
use PbConnect\Security\CredentialVault;
use PbConnect\Security\PaymentRequestToken;
use PbConnect\Support\DebugInfoBuilder;
use PbConnect\Support\ModuleTranslation;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Pbconnect extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'pbconnect';
        $this->tab = 'payments_gateways';
        $this->version = '5.0.1';
        $this->author = 'PB Integrações';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('PB Connect', [], ModuleTranslation::DOMAIN_MODULE);
        $this->description = $this->trans(
            'Accept PIX, credit card, boleto and Checkout PB with the best providers.',
            [],
            ModuleTranslation::DOMAIN_MODULE
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall PB Connect?',
            [],
            ModuleTranslation::DOMAIN_MODULE
        );
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function transAdmin(string $wording, array $parameters = []): string
    {
        return $this->trans($wording, $parameters, ModuleTranslation::DOMAIN_ADMIN);
    }

    public function transShop(string $wording, array $parameters = [], ?string $locale = null): string
    {
        return $this->trans($wording, $parameters, ModuleTranslation::DOMAIN_SHOP, $locale);
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return (new Installer($this))->install();
    }

    public function uninstall(): bool
    {
        return (new Installer($this))->uninstall() && parent::uninstall();
    }

    public function getContent(): string
    {
        (new Installer($this))->ensureRuntimeSetup();

        return (new ConfigFormPresenter(
            $this,
            $this->context->link,
            (int) $this->context->language->id
        ))->render();
    }

    /**
     * Public factory — helpers must not read protected $context / $smarty.
     */
    public function urls(): PaymentUrlBuilder
    {
        return new PaymentUrlBuilder(
            $this->name,
            $this->context->link,
            (int) $this->context->shop->id
        );
    }

    /**
     * @param array<string, mixed> $assign
     */
    public function renderTemplate(string $template, array $assign = []): string
    {
        if ($assign !== []) {
            $this->smarty->assign($assign);
        }

        return $this->fetch($template);
    }

    public function hookPaymentOptions(array $params): array
    {
        if (!CredentialVault::hasConnectKey()) {
            return [];
        }

        $cart = $params['cart'] ?? $this->context->cart;
        if (!$cart || !$cart->id) {
            return [];
        }

        $options = [];
        $urls = $this->urls();
        $idCart = (int) $cart->id;
        PaymentRequestToken::ensureSecret();

        if (Configuration::get(ConfigurationKeys::PIX_ENABLED) === '1') {
            $option = new PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText(Configuration::get(ConfigurationKeys::PIX_TITLE) ?: $this->transShop('PIX'));
            $option->setAction($urls->validationUrl('pix', $cart));
            $this->attachPaymentLogo($option, 'pix.svg');
            $options[] = $option;
        }

        if (Configuration::get(ConfigurationKeys::BOLETO_ENABLED) === '1') {
            $option = new PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText(Configuration::get(ConfigurationKeys::BOLETO_TITLE) ?: $this->transShop('Boleto'));
            $option->setAction($urls->validationUrl('boleto', $cart));
            $this->attachPaymentLogo($option, 'boleto.svg');
            $options[] = $option;
        }

        if (Configuration::get(ConfigurationKeys::CHECKOUT_PB_ENABLED) === '1') {
            $option = new PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText(
                Configuration::get(ConfigurationKeys::CHECKOUT_PB_TITLE) ?: $this->transShop('Pay on Checkout PB')
            );
            $option->setAction($urls->validationUrl('checkout_pb', $cart));
            $this->attachPaymentLogo($option, 'checkout-pb.svg');
            $options[] = $option;
        }

        if (Configuration::get(ConfigurationKeys::CC_ENABLED) === '1') {
            $cardAction = $urls->validationUrl('card', $cart);
            $shopId = (int) $this->context->shop->id;
            $this->smarty->assign([
                'pb_card_action' => $cardAction,
                'pb_payment_token' => PaymentRequestToken::forCart($idCart, $shopId),
                'pb_id_cart' => $idCart,
                'pb_cart_key' => (string) $cart->secure_key,
                'pb_img_url' => rtrim($this->getPathUri(), '/') . '/views/img/',
            ]);
            $cardForm = $this->fetch('module:pbconnect/views/templates/hook/payment_card_option.tpl');

            $option = new PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText(Configuration::get(ConfigurationKeys::CC_TITLE) ?: $this->transShop('Credit card'));
            $option->setForm($cardForm);
            $option->setAction($cardAction);
            $this->attachPaymentLogo($option, 'cc.svg');
            $options[] = $option;
        }

        return $options;
    }

    public function hookPaymentReturn(array $params): string
    {
        if (!isset($params['order']) || (string) $params['order']->module !== $this->name) {
            return '';
        }

        $order = $params['order'];
        $idOrder = (int) $order->id;
        $repo = new TransactionRepository();
        $transactions = $repo->findByOrder($idOrder);
        $payload = $repo->getLatestPayload($idOrder);
        $pixPayload = $repo->findPixPayloadForOrder($idOrder) ?? $payload;
        $pix = null;
        $boleto = null;
        $pixExpiresAt = '';
        if (is_array($pixPayload) && !empty($pixPayload['qr_codes'])) {
            $pix = PbConnect\Payment\PixPaymentHandler::extractQrDisplay($pixPayload);
            if (!empty($pix['image_url']) && PbConnect\Support\PixQrProxy::needsProxy($pix['image_url'])) {
                $pix['image_url'] = $this->urls()->pixQrProxyUrl((int) $order->id_cart);
            }
            $rawExp = (string) ($pixPayload['qr_codes'][0]['expiration_date'] ?? '');
            if ($rawExp !== '') {
                $ts = strtotime($rawExp);
                $pixExpiresAt = $ts ? date('d/m/Y H:i', $ts) : $rawExp;
            }
        }
        if (is_array($payload)
            && !empty($payload['charges'][0]['payment_method']['type'])
            && strtoupper((string) $payload['charges'][0]['payment_method']['type']) === 'BOLETO'
        ) {
            $boleto = PbConnect\Payment\BoletoPaymentHandler::extractBoletoDisplay($payload);
        }

        $mapper = new OrderMapper();
        $paid = (int) $order->getCurrentState() === $mapper->getPaidOrderStateId();
        $statusUrl = '';
        if ($pix && !$paid) {
            $statusUrl = $this->context->link->getModuleLink($this->name, 'process', [
                'action' => 'order_status',
                'id_order' => $idOrder,
                'key' => (string) $order->secure_key,
            ], true);
        }

        $this->registerPaymentReturnAssets((bool) $pix);

        $this->smarty->assign([
            'order_reference' => $order->reference,
            'status' => 'ok',
            'pb_transactions' => $transactions,
            'pb_pix' => $pix,
            'pb_pix_paid' => $paid,
            'pb_pix_expires_at' => $pixExpiresAt,
            'pb_pix_status_url' => $statusUrl,
            'pb_pix_order_id' => $idOrder,
            'pb_pix_copy_icon' => $this->getPathUri() . 'views/img/copy-icon.svg',
            'pb_boleto' => $boleto,
        ]);

        return $this->fetch('module:pbconnect/views/templates/hook/payment_return.tpl');
    }

    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if (!$idOrder) {
            return '';
        }

        (new Installer($this))->ensureRuntimeSetup();

        $transactions = (new TransactionRepository())->findByOrder($idOrder);
        if ($transactions === []) {
            return '';
        }

        $payload = (new TransactionRepository())->getLatestPayload($idOrder) ?? [];
        $cardView = \PbConnect\Admin\OrderPaymentView::fromPayload(
            $payload,
            (string) ($transactions[0]['payment_method'] ?? ''),
            (string) ($transactions[0]['status'] ?? '')
        );
        if ($cardView['pb_charge_id'] === '' && !empty($transactions[0]['pb_charge_id'])) {
            $cardView['pb_charge_id'] = (string) $transactions[0]['pb_charge_id'];
            $cardView['vindi_url'] = \PbConnect\Support\PartnerPortal::vindiPortalUrl(
                $cardView['partner_bill_id'],
                $cardView['pb_charge_id']
            );
        }
        if ($cardView['pb_order_id'] === '' && !empty($transactions[0]['pb_order_id'])) {
            $cardView['pb_order_id'] = (string) $transactions[0]['pb_order_id'];
        }

        // Backfill native PrestaShop payment block when payload has card / txn data.
        if ($payload !== []) {
            \PbConnect\Payment\OrderPaymentSync::syncFromPayload($idOrder, $payload);
        }

        $brandIconFile = \PbConnect\Admin\OrderPaymentView::brandIconFile(
            (string) ($cardView['card_brand_key'] ?? $cardView['card_brand'] ?? '')
        );
        $cardView['card_brand_icon'] = $brandIconFile !== ''
            ? rtrim($this->getPathUri(), '/') . '/views/img/credit-cards/' . $brandIconFile
            : '';

        // Prefer chk_ for force-update: declined retries share the session and order_ids grows.
        $pbOrderId = \PbConnect\Admin\ForceOrderUpdateHandler::findCheckoutSessionId($transactions);
        if ($pbOrderId === '') {
            $pbOrderId = (string) ($cardView['pb_order_id'] ?? '');
        }
        if ($pbOrderId === '' && !empty($transactions[0]['pb_order_id'])) {
            $pbOrderId = (string) $transactions[0]['pb_order_id'];
        }
        $forceUrl = '';
        if ($pbOrderId !== '') {
            $forceUrl = $this->context->link->getAdminLink('AdminPbconnectForceUpdate', true, [], [
                'id_order' => $idOrder,
                'pb_order_id' => $pbOrderId,
            ]);
        }

        $forceFlash = null;
        if (isset($this->context->cookie->pbconnect_force_msg)
            && (string) $this->context->cookie->pbconnect_force_msg !== ''
        ) {
            $forceFlash = [
                'ok' => (string) ($this->context->cookie->pbconnect_force_ok ?? '0') === '1',
                'message' => (string) $this->context->cookie->pbconnect_force_msg,
            ];
            $this->context->cookie->__unset('pbconnect_force_ok');
            $this->context->cookie->__unset('pbconnect_force_msg');
            $this->context->cookie->write();
        }

        $this->smarty->assign([
            'pb_transactions' => $transactions,
            'pb_card' => $cardView,
            'pb_force_flash' => $forceFlash,
            'pb_actions' => [
                'vindi_url' => $cardView['vindi_url'],
                'yapay_url' => $cardView['yapay_url'],
                'force_update_url' => $forceUrl,
            ],
        ]);

        return $this->fetch('module:pbconnect/views/templates/admin/order_info.tpl');
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        return $this->hookDisplayAdminOrderMain($params);
    }

    public function hookActionFrontControllerSetMedia(): void
    {
        $phpSelf = (string) ($this->context->controller->php_self ?? '');
        if ($phpSelf === 'order-confirmation') {
            $this->registerPaymentReturnAssets(true);

            return;
        }

        if ($phpSelf !== 'order' && $phpSelf !== 'checkout') {
            return;
        }

        if (!CredentialVault::hasConnectKey()) {
            return;
        }

        $ccEnabled = Configuration::get(ConfigurationKeys::CC_ENABLED) === '1';
        if (!$ccEnabled) {
            return;
        }

        $cssRel = 'modules/' . $this->name . '/views/css/checkout.css';
        $jsRel = 'modules/' . $this->name . '/views/js/checkout.js';
        $cssPath = _PS_ROOT_DIR_ . '/' . $cssRel;
        $jsPath = _PS_ROOT_DIR_ . '/' . $jsRel;

        $this->context->controller->registerStylesheet(
            'pbconnect-checkout',
            $cssRel,
            [
                'media' => 'all',
                'priority' => 110,
                'version' => is_file($cssPath) ? (string) filemtime($cssPath) : $this->version,
            ]
        );
        $this->context->controller->registerJavascript(
            'pbconnect-checkout',
            $jsRel,
            [
                'position' => 'bottom',
                'priority' => 110,
                'version' => is_file($jsPath) ? (string) filemtime($jsPath) : $this->version,
            ]
        );

        $idCart = (int) $this->context->cart->id;
        $shopId = (int) $this->context->shop->id;
        PaymentRequestToken::ensureSecret();
        Media::addJsDef([
            'pbConnectConfig' => [
                'processUrl' => $this->context->link->getModuleLink($this->name, 'process', [
                    'id_cart' => $idCart,
                    'token' => PaymentRequestToken::forCart($idCart, $shopId),
                ], true),
                'threeDsReturnUrl' => $this->urls()->threeDsReturnUrl($this->context->cart),
                'paymentToken' => PaymentRequestToken::forCart($idCart, $shopId),
                'idCart' => $idCart,
                'sandbox' => CredentialVault::isSandbox(),
                'creditCardEnabled' => true,
                'installmentsEnabled' => Configuration::get(ConfigurationKeys::CC_INSTALLMENTS) === '1',
                'maxInstallments' => (int) Configuration::get(ConfigurationKeys::CC_MAX_INSTALLMENTS),
                'threeDsEnabled' => Configuration::get(ConfigurationKeys::CC_3DS_ENABLED) === '1',
                'threeDsMinCents' => (int) Configuration::get(ConfigurationKeys::CC_3DS_MIN_CENTS),
                'threeDsAllowContinue' => Configuration::get(ConfigurationKeys::CC_3DS_ALLOW_CONTINUE) === '1',
                'cartTotalCents' => (new OrderMapper())->cartTotalCents($this->context->cart),
            ],
        ]);
    }

    public function hookModuleRoutes(): array
    {
        return [
            'pbconnect-notif-short' => [
                'rule' => 'pbc/n/{id_cart}/{hash}',
                'keywords' => [
                    'id_cart' => ['regexp' => '[0-9]+', 'param' => 'id_cart'],
                    'hash' => ['regexp' => '[a-f0-9]{4}', 'param' => 'h'],
                ],
                'controller' => 'notif',
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
        ];
    }

    public function hookActionDispatcherBefore(array $params): void
    {
        if (Tools::getValue('pb-api') !== 'pb_connect_info') {
            return;
        }
        $this->renderDebugJson();
        exit;
    }

    public function renderDebugJson(): void
    {
        if (Configuration::get(ConfigurationKeys::DEBUG_MODE) !== '1') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'Debug mode disabled']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode((new DebugInfoBuilder($this))->build(), JSON_PRETTY_PRINT);
    }

    public function getClient(): PbClient
    {
        return new PbClient($this->version);
    }

    private function attachPaymentLogo(PaymentOption $option, string $filename): void
    {
        $abs = _PS_MODULE_DIR_ . $this->name . '/views/img/' . $filename;
        if (is_file($abs)) {
            $option->setLogo(Media::getMediaPath($abs));
        }
    }

    /**
     * Create the PrestaShop order after the gateway accepted the payment intent / charge.
     * $amountPaid must come from the external system response (checklist rule).
     *
     * @param bool $fromWebhook skip FO customer-session checks (HMAC already verified)
     */
    /**
     * @param array<string, mixed> $extraVars PrestaShop PaymentModule extras (e.g. transaction_id)
     */
    public function validateOrderFromGateway(
        Cart $cart,
        int $orderStateId,
        float $amountPaid,
        string $method,
        bool $fromWebhook = false,
        array $extraVars = [],
    ): int {
        if ($fromWebhook) {
            CartGuard::assertCartForWebhook((int) $cart->id);
        } else {
            CartGuard::assertPayableCart(
                $cart,
                null,
                (int) ($this->context->customer->id ?? 0)
            );
        }
        if ($amountPaid <= 0) {
            throw new PrestaShopException('Invalid gateway amount for validateOrder.');
        }
        if ($cart->orderExists()) {
            return (int) Order::getIdByCartId((int) $cart->id);
        }

        $currency = new Currency((int) $cart->id_currency);

        $this->validateOrder(
            (int) $cart->id,
            $orderStateId,
            $amountPaid,
            $this->displayName . ' - ' . $method,
            null,
            $extraVars,
            (int) $currency->id,
            false,
            $cart->secure_key
        );

        return (int) $this->currentOrder;
    }

    private function registerPaymentReturnAssets(bool $withPixJs): void
    {
        if (!isset($this->context->controller) || !is_object($this->context->controller)) {
            return;
        }

        $cssRel = 'modules/' . $this->name . '/views/css/payment_return.css';
        $cssPath = _PS_ROOT_DIR_ . '/' . $cssRel;
        $this->context->controller->registerStylesheet(
            'pbconnect-payment-return',
            $cssRel,
            [
                'media' => 'all',
                'priority' => 150,
                'version' => is_file($cssPath) ? (string) filemtime($cssPath) : $this->version,
            ]
        );

        if (!$withPixJs) {
            return;
        }

        $jsRel = 'modules/' . $this->name . '/views/js/payment_return.js';
        $jsPath = _PS_ROOT_DIR_ . '/' . $jsRel;
        $this->context->controller->registerJavascript(
            'pbconnect-payment-return',
            $jsRel,
            [
                'position' => 'bottom',
                'priority' => 150,
                'version' => is_file($jsPath) ? (string) filemtime($jsPath) : $this->version,
            ]
        );
    }
}
