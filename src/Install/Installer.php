<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Install;

use PbConnect\Config\ConfigurationKeys;
use PbConnect\Security\PaymentRequestToken;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Installer
{
    private \PaymentModule $module;

    public function __construct(\PaymentModule $module)
    {
        $this->module = $module;
    }

    /** @var list<string> */
    public const HOOKS = [
        'paymentOptions',
        'paymentReturn',
        'displayAdminOrderMain',
        'displayAdminOrderSide',
        'actionFrontControllerSetMedia',
        'moduleRoutes',
        'actionDispatcherBefore',
    ];

    public function install(): bool
    {
        return $this->installTables()
            && $this->installConfiguration()
            && $this->ensureRuntimeSetup()
            && $this->warmTranslationCache();
    }

    public function ensureRuntimeSetup(): bool
    {
        return $this->registerHooks()
            && $this->registerShopRestrictions()
            && $this->registerAdminTabs();
    }

    public function uninstall(): bool
    {
        return $this->uninstallAdminTabs()
            && $this->uninstallTables()
            && $this->uninstallConfiguration();
    }

    private function installTables(): bool
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/sql/install.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split('/;\s*[\r\n]+/', $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            if (!\Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTables(): bool
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/sql/uninstall.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split('/;\s*[\r\n]+/', $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            \Db::getInstance()->execute($query);
        }

        return true;
    }

    private function installConfiguration(): bool
    {
        foreach (ConfigurationKeys::defaults() as $key => $value) {
            if (!\Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        PaymentRequestToken::ensureSecret();

        return true;
    }

    private function uninstallConfiguration(): bool
    {
        foreach (array_keys(ConfigurationKeys::defaults()) as $key) {
            \Configuration::deleteByName($key);
        }
        \Configuration::deleteByName(ConfigurationKeys::CONNECT_KEY);
        \Configuration::deleteByName('PBCONNECT_REQUEST_SECRET');

        return true;
    }

    private function registerHooks(): bool
    {
        foreach (self::HOOKS as $hook) {
            if ($this->isHookRegistered($hook)) {
                continue;
            }
            if (!$this->module->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    private function isHookRegistered(string $hookName): bool
    {
        $idModule = (int) $this->module->id;
        if ($idModule <= 0) {
            return false;
        }

        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module` hm
            INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`
            WHERE hm.`id_module` = ' . $idModule . '
            AND h.`name` = "' . pSQL($hookName) . '"'
        );
    }

    private function registerShopRestrictions(): bool
    {
        if (!$this->module instanceof \PaymentModule) {
            return false;
        }

        $idModule = (int) $this->module->id;
        if ($idModule <= 0) {
            return false;
        }

        $shops = \Shop::getShops(true, null, true);
        if (!is_array($shops) || $shops === []) {
            $shops = [(int) \Configuration::get('PS_SHOP_DEFAULT')];
        }

        \Db::getInstance()->delete('module_country', 'id_module = ' . $idModule);
        \Db::getInstance()->delete('module_currency', 'id_module = ' . $idModule);
        \Db::getInstance()->delete('module_carrier', 'id_module = ' . $idModule);

        return $this->module->addCheckboxCountryRestrictionsForModule($shops)
            && $this->module->addCheckboxCurrencyRestrictionsForModule($shops)
            && $this->module->addCheckboxCarrierRestrictionsForModule($shops);
    }

    private function warmTranslationCache(): bool
    {
        if (method_exists($this->module, 'isUsingNewTranslationSystem') && $this->module->isUsingNewTranslationSystem()) {
            try {
                $this->module->getTranslator();
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        return true;
    }

    private function registerAdminTabs(): bool
    {
        $className = 'AdminPbconnectForceUpdate';
        $idTab = (int) \Tab::getIdFromClassName($className);
        if ($idTab > 0) {
            return true;
        }

        $tab = new \Tab();
        $tab->active = true;
        $tab->class_name = $className;
        $tab->name = [];
        foreach (\Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'PB Connect Force Update';
        }
        $tab->id_parent = -1; // hidden from menu
        $tab->module = $this->module->name;
        $tab->icon = 'refresh';

        return (bool) $tab->add();
    }

    private function uninstallAdminTabs(): bool
    {
        $idTab = (int) \Tab::getIdFromClassName('AdminPbconnectForceUpdate');
        if ($idTab <= 0) {
            return true;
        }
        $tab = new \Tab($idTab);

        return (bool) $tab->delete();
    }
}
