<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Support;

use PbConnect\Api\PbClient;
use PbConnect\Config\ConfigurationKeys;
use PbConnect\Security\CredentialVault;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class DebugInfoBuilder
{
    private \PaymentModule $module;

    public function __construct(\PaymentModule $module)
    {
        $this->module = $module;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $info = [
            'module' => $this->module->name,
            'version' => $this->module->version,
            'ps_version' => _PS_VERSION_,
            'sandbox' => CredentialVault::isSandbox(),
            'has_connect_key' => CredentialVault::hasConnectKey(),
            'debug' => \Configuration::get(ConfigurationKeys::DEBUG_MODE) === '1',
            'methods' => [
                'pix' => \Configuration::get(ConfigurationKeys::PIX_ENABLED) === '1',
                'boleto' => \Configuration::get(ConfigurationKeys::BOLETO_ENABLED) === '1',
                'cc' => \Configuration::get(ConfigurationKeys::CC_ENABLED) === '1',
                'checkout_pb' => \Configuration::get(ConfigurationKeys::CHECKOUT_PB_ENABLED) === '1',
                '3ds' => \Configuration::get(ConfigurationKeys::CC_3DS_ENABLED) === '1',
            ],
        ];

        if (CredentialVault::hasConnectKey()) {
            try {
                $info['connectInfo'] = (new PbClient($this->module->version))->connectInfo();
            } catch (\Throwable $e) {
                $info['connectInfo_error'] = $e->getMessage();
            }
        }

        return $info;
    }
}
