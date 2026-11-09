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

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ModuleTranslation
{
    public const DOMAIN_MODULE = 'Modules.Pbconnect.Pbconnect';
    public const DOMAIN_ADMIN = 'Modules.Pbconnect.Admin';
    public const DOMAIN_SHOP = 'Modules.Pbconnect.Shop';
}
