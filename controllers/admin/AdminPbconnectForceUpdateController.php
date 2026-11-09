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

use PbConnect\Admin\ForceOrderUpdateHandler;

/**
 * Hidden admin endpoint: force-refresh PB order from API (like Woo "Forçar atualização").
 */
class AdminPbconnectForceUpdateController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    public function initContent(): void
    {
        $idOrder = (int) Tools::getValue('id_order');
        $pbOrderId = trim((string) Tools::getValue('pb_order_id'));

        /** @var Pbconnect|false $module */
        $module = Module::getInstanceByName('pbconnect');
        if (!$module instanceof Pbconnect || !$module->active) {
            $this->flashAndRedirect($idOrder, false, 'Módulo PB Connect inativo.');

            return;
        }

        if ($idOrder <= 0 || $pbOrderId === '') {
            $this->flashAndRedirect($idOrder, false, 'Faltando id_order ou pb_order_id.');

            return;
        }

        $result = (new ForceOrderUpdateHandler())->run($module, $idOrder, $pbOrderId);
        $this->flashAndRedirect($idOrder, $result['ok'], $result['message']);
    }

    private function flashAndRedirect(int $idOrder, bool $ok, string $message): void
    {
        $this->context->cookie->__set('pbconnect_force_ok', $ok ? '1' : '0');
        $this->context->cookie->__set('pbconnect_force_msg', Tools::substr($message, 0, 512));
        $this->context->cookie->write();

        $url = $this->context->link->getAdminLink('AdminOrders', true, [], [
            'id_order' => max(1, $idOrder),
            'vieworder' => 1,
        ]);
        Tools::redirectAdmin($url);
    }
}
