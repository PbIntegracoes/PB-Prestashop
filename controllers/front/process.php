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
use PbConnect\Payment\FeesCalculateQueryBuilder;
use PbConnect\Payment\PixPaymentHandler;
use PbConnect\Repository\TransactionRepository;
use PbConnect\Security\CredentialVault;
use PbConnect\Security\PaymentRequestToken;
use PbConnect\Support\PixQrProxy;

class PbconnectProcessModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        $this->ajax = true;
        $this->display_header = false;
        $this->display_footer = false;
        $action = Tools::getValue('action');

        if ($action === 'pix_qr') {
            $this->servePixQr();
        }

        header('Content-Type: application/json');
        /** @var Pbconnect $module */
        $module = $this->module;

        try {
            $this->assertRequestToken();
            $client = $module->getClient();
            $jsonBody = $this->jsonBody();

            switch ($action) {
                case 'tokenize_config':
                    echo json_encode($client->tokenizeConfig());
                    break;

                case 'fees':
                    $cart = $this->context->cart;
                    $value = (new OrderMapper())->cartTotalCents($cart);
                    $bin = Tools::getValue('bin', $jsonBody['bin'] ?? '555566');
                    $query = FeesCalculateQueryBuilder::build($value, (string) $bin);
                    echo json_encode($client->feesCalculate($query));
                    break;

                case 'payment_methods_3ds':
                    echo json_encode($client->paymentMethods('code:credit_card_3ds'));
                    break;

                case 'payment_profile':
                    $token = (string) ($jsonBody['gateway_token'] ?? '');
                    $company = (string) ($jsonBody['payment_company_code'] ?? 'mastercard');
                    if ($token === '') {
                        throw new InvalidArgumentException('gateway_token missing');
                    }
                    $customer = $this->buildCustomerPayload();
                    $resp = $client->createPaymentProfile([
                        'gateway_token' => $token,
                        'payment_company_code' => $company,
                        'payment_method_code' => 'credit_card',
                        'customer' => $customer,
                    ]);
                    $id = $resp['payment_profile']['id'] ?? ($resp['id'] ?? null);
                    echo json_encode([
                        'payment_profile_id' => (int) $id,
                        'payment_profile' => $resp['payment_profile'] ?? $resp,
                    ]);
                    break;

                case '3ds_setup':
                    $profileId = (int) ($jsonBody['payment_profile_id'] ?? 0);
                    if ($profileId <= 0) {
                        throw new InvalidArgumentException('payment_profile_id invalid');
                    }
                    $resp = $client->threeDsSetup([
                        'payment_profile_id' => $profileId,
                        'payment_method_code' => 'credit_card_3ds',
                    ]);
                    echo json_encode(['setup' => $resp['setup'] ?? $resp]);
                    break;

                case '3ds_enroll':
                    $sessionId = (string) ($jsonBody['session_id'] ?? '');
                    $amountCents = (int) ($jsonBody['amount'] ?? 0);
                    $installments = max(1, (int) ($jsonBody['installments'] ?? 1));
                    $device = is_array($jsonBody['device_info'] ?? null) ? $jsonBody['device_info'] : [];
                    if ($sessionId === '' || $amountCents < 100) {
                        throw new InvalidArgumentException('Incomplete enroll data');
                    }
                    $payload = $this->buildEnrollPayload($module, $sessionId, $amountCents, $installments, $device);
                    $resp = $client->threeDsEnroll($payload);
                    $enroll = $resp['enroll'] ?? $resp;
                    if (is_array($enroll) && isset($enroll['enroll']) && is_array($enroll['enroll'])) {
                        $enroll = $enroll['enroll'];
                    }
                    echo json_encode(['enroll' => $enroll]);
                    break;

                case '3ds_validate':
                    $txid = (string) ($jsonBody['authentication_transaction_id'] ?? '');
                    $cardType = (string) ($jsonBody['card_type'] ?? 'visa');
                    if ($txid === '') {
                        throw new InvalidArgumentException('TransactionId missing');
                    }
                    $resp = $client->threeDsValidate([
                        'authentication_transaction_id' => $txid,
                        'card_type' => $cardType,
                    ]);
                    echo json_encode(['validate' => $resp['validate'] ?? $resp]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Unknown action']);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }

    private function assertRequestToken(): void
    {
        $idCart = (int) Tools::getValue('id_cart', $this->context->cart->id ?? 0);
        $token = (string) Tools::getValue('token');
        $shopId = (int) $this->context->shop->id;
        if (!PaymentRequestToken::validate($idCart, $token, $shopId)) {
            throw new InvalidArgumentException('Invalid AJAX payment token.');
        }
    }

    private function servePixQr(): void
    {
        $idCart = (int) Tools::getValue('id_cart');
        $hash = (string) Tools::getValue('h');
        $token = (string) Tools::getValue('token');
        $shopId = (int) $this->context->shop->id;
        if (!CredentialVault::validateWebhookHash($idCart, $hash)
            || !PaymentRequestToken::validate($idCart, $token, $shopId)
        ) {
            http_response_code(403);
            exit;
        }

        $idOrder = (int) Order::getIdByCartId($idCart);
        $payload = $idOrder > 0 ? (new TransactionRepository())->getLatestPayload($idOrder) : null;
        if (!is_array($payload)) {
            // Fallback: latest by cart via raw query in repo if needed
            http_response_code(404);
            exit;
        }

        $pix = PixPaymentHandler::extractQrDisplay($payload);
        $url = $pix['image_url'];
        if ($url === '') {
            http_response_code(404);
            exit;
        }

        PixQrProxy::fetchAndStream($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $raw = Tools::file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array{name: string, email: string, registry_code: string, mobile_phone: string}
     */
    private function buildCustomerPayload(): array
    {
        $cart = $this->context->cart;
        $customer = new Customer((int) $cart->id_customer);
        $address = new Address((int) $cart->id_address_invoice);
        $mapper = new OrderMapper();
        $built = $mapper->buildCustomer($customer, $address);
        $phone = $built['phones'][0] ?? ['country' => '55', 'area' => '11', 'number' => '999999999'];
        $mobile = $phone['country'] . $phone['area'] . $phone['number'];

        return [
            'name' => $built['name'],
            'email' => $built['email'],
            'registry_code' => $built['tax_id'],
            'mobile_phone' => $mobile,
        ];
    }

    /**
     * @param array<string, mixed> $device
     *
     * @return array<string, mixed>
     */
    private function buildEnrollPayload(
        Pbconnect $module,
        string $sessionId,
        int $amountCents,
        int $installments,
        array $device,
    ): array {
        $cart = $this->context->cart;
        $customer = new Customer((int) $cart->id_customer);
        $address = new Address((int) $cart->id_address_invoice);
        $mapper = new OrderMapper();
        $built = $mapper->buildCustomer($customer, $address);
        $addr = $mapper->buildCustomerAddress($address);
        $phone = $built['phones'][0] ?? ['country' => '55', 'area' => '11', 'number' => '999999999'];
        $mobile = $phone['country'] . $phone['area'] . $phone['number'];
        $urls = $module->urls();

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $registry = preg_replace('/\D+/', '', (string) $built['tax_id']) ?: '00000000000';
        $mobileDigits = preg_replace('/\D+/', '', $mobile) ?: '';
        if ($mobileDigits !== '' && !str_starts_with($mobileDigits, '55')) {
            $mobileDigits = '55' . $mobileDigits;
        }
        if (strlen($mobileDigits) < 12) {
            $mobileDigits = '5511999999999';
        }

        // Prefer HTTPS merchant/return URLs — Cardinal/Vindi often reject plain http://localhost.
        $shopUrl = $this->context->link->getBaseLink(null, true);
        if ($shopUrl === '' || !str_starts_with($shopUrl, 'http')) {
            $shopUrl = $this->context->link->getBaseLink();
        }

        return [
            'session_id' => $sessionId,
            'amount' => round($amountCents / 100, 2),
            'currency' => 'BRL',
            'installments' => $installments,
            'return_url' => $urls->threeDsReturnUrl($cart),
            'client_reference_code' => 'ps_' . time(),
            'device_info' => [
                'ip_address' => (string) ($device['ip_address'] ?? $ip),
                'user_agent' => (string) ($device['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0')),
                'screen_height' => (int) ($device['screen_height'] ?? 1080),
                'screen_width' => (int) ($device['screen_width'] ?? 1920),
                'color_depth' => (int) ($device['color_depth'] ?? 24),
                'timezone_offset' => (int) ($device['timezone_offset'] ?? -180),
                'language' => (string) ($device['language'] ?? 'pt-BR'),
                'java_enabled' => !empty($device['java_enabled']),
            ],
            'billing_address' => [
                'name' => $built['name'],
                'email' => $built['email'] !== '' ? $built['email'] : 'cliente@exemplo.test',
                'street' => trim($addr['street'] . ', ' . $addr['number']),
                'city' => $addr['city'] !== '' ? $addr['city'] : 'Sao Paulo',
                'state' => $addr['region_code'] !== '' ? $addr['region_code'] : 'SP',
                'postal_code' => $addr['postal_code'] !== '' ? $addr['postal_code'] : '01310100',
                'country' => 'BR',
            ],
            'buyer_information' => [
                'registry_code' => $registry,
                'mobile_phone' => $mobileDigits,
            ],
            'merchant_information' => [
                'merchant_name' => Tools::substr((string) Configuration::get('PS_SHOP_NAME'), 0, 40) ?: 'Loja',
                'url' => $shopUrl,
            ],
        ];
    }
}
