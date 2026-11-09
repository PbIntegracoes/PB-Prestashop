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

use PbConnect\Config\ConfigurationKeys;
use PbConnect\Webhook\NotificationProcessor;

class PbconnectNotifModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        $this->ajax = true;
        $this->display_header = false;
        $this->display_footer = false;
        parent::initContent();
    }

    public function postProcess(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            exit;
        }

        // Prefer id_cart (current). Accept legacy id_order only as fallback alias for cart lookup.
        $idCart = (int) Tools::getValue('id_cart');
        if ($idCart <= 0) {
            $idOrderLegacy = (int) Tools::getValue('id_order');
            if ($idOrderLegacy > 0) {
                $order = new Order($idOrderLegacy);
                if (Validate::isLoadedObject($order)) {
                    $idCart = (int) $order->id_cart;
                }
            }
        }

        $hash = (string) Tools::getValue('h');
        $raw = Tools::file_get_contents('php://input');
        $payload = json_decode($raw ?: '{}', true);

        if (!is_array($payload) || $idCart <= 0) {
            header('HTTP/1.1 400 Bad Request');
            exit;
        }

        try {
            (new NotificationProcessor($this->module))->process($idCart, $hash, $payload);
            http_response_code(200);
            echo 'OK';
        } catch (Throwable $e) {
            if (Configuration::get(ConfigurationKeys::DEBUG_MODE) === '1') {
                PrestaShopLogger::addLog('PB Connect webhook: ' . $e->getMessage(), 3);
            }
            http_response_code(400);
            echo 'ERR';
        }
        exit;
    }
}
