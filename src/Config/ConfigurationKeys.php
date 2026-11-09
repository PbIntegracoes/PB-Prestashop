<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ConfigurationKeys
{
    public const CONNECT_KEY = 'PB_CONNECT_KEY';
    public const DEBUG_MODE = 'PB_DEBUG_MODE';
    public const USE_PROXY = 'PB_USE_PROXY';

    public const OS_WAITING = 'PB_OS_WAITING';
    public const OS_PAID = 'PB_OS_PAID';
    public const OS_ERROR = 'PB_OS_ERROR';

    public const CC_ENABLED = 'PB_CC_ENABLED';
    public const CC_TITLE = 'PB_CC_TITLE';
    public const CC_DESCRIPTION = 'PB_CC_DESCRIPTION';
    public const CC_INSTALLMENTS = 'PB_CC_INSTALLMENTS';
    public const CC_MAX_INSTALLMENTS = 'PB_CC_MAX_INSTALLMENTS';
    public const CC_MAX_NO_INTEREST = 'PB_CC_MAX_NO_INTEREST';
    public const CC_INSTALLMENT_MODE = 'PB_CC_INSTALLMENT_MODE';
    /** Minimum installment value in centavos (R$ 50,00 = 5000). */
    public const CC_MIN_INSTALLMENT_CENTS = 'PB_CC_MIN_INSTALLMENT_CENTS';
    public const CC_3DS_ENABLED = 'PB_CC_3DS_ENABLED';
    public const CC_3DS_MIN_CENTS = 'PB_CC_3DS_MIN_CENTS';
    public const CC_3DS_ALLOW_CONTINUE = 'PB_CC_3DS_ALLOW_CONTINUE';
    public const CC_MIN_TOTAL_CENTS = 'PB_CC_MIN_TOTAL_CENTS';

    public const PIX_ENABLED = 'PB_PIX_ENABLED';
    public const PIX_TITLE = 'PB_PIX_TITLE';
    public const PIX_DESCRIPTION = 'PB_PIX_DESCRIPTION';
    public const PIX_EXPIRY_MINUTES = 'PB_PIX_EXPIRY_MINUTES';
    public const PIX_DISCOUNT = 'PB_PIX_DISCOUNT';

    public const BOLETO_ENABLED = 'PB_BOLETO_ENABLED';
    public const BOLETO_TITLE = 'PB_BOLETO_TITLE';
    public const BOLETO_DESCRIPTION = 'PB_BOLETO_DESCRIPTION';
    public const BOLETO_EXPIRY_DAYS = 'PB_BOLETO_EXPIRY_DAYS';
    public const BOLETO_DISCOUNT = 'PB_BOLETO_DISCOUNT';

    public const CHECKOUT_PB_ENABLED = 'PB_CHECKOUT_PB_ENABLED';
    public const CHECKOUT_PB_TITLE = 'PB_CHECKOUT_PB_TITLE';
    public const CHECKOUT_PB_DESCRIPTION = 'PB_CHECKOUT_PB_DESCRIPTION';
    public const CHECKOUT_PB_METHODS = 'PB_CHECKOUT_PB_METHODS';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            self::DEBUG_MODE => '0',
            self::USE_PROXY => '0',
            self::OS_WAITING => (string) \Configuration::get('PS_OS_BANKWIRE'),
            self::OS_PAID => (string) \Configuration::get('PS_OS_PAYMENT'),
            self::OS_ERROR => (string) \Configuration::get('PS_OS_ERROR'),
            self::CC_ENABLED => '1',
            self::CC_TITLE => 'Cartão de crédito',
            self::CC_DESCRIPTION => 'Pague com cartão via PB Connect',
            self::CC_INSTALLMENTS => '1',
            self::CC_MAX_INSTALLMENTS => '12',
            self::CC_MAX_NO_INTEREST => '3',
            self::CC_INSTALLMENT_MODE => InstallmentMode::MERCHANT_UPTO_N,
            self::CC_MIN_INSTALLMENT_CENTS => '5000',
            self::CC_3DS_ENABLED => '0',
            self::CC_3DS_MIN_CENTS => '100',
            self::CC_3DS_ALLOW_CONTINUE => '0',
            self::CC_MIN_TOTAL_CENTS => '100',
            self::PIX_ENABLED => '1',
            self::PIX_TITLE => 'PIX',
            self::PIX_DESCRIPTION => 'Pagamento instantâneo via PIX',
            self::PIX_EXPIRY_MINUTES => '1440',
            self::PIX_DISCOUNT => '0',
            self::BOLETO_ENABLED => '1',
            self::BOLETO_TITLE => 'Boleto',
            self::BOLETO_DESCRIPTION => 'Pague com boleto bancário',
            self::BOLETO_EXPIRY_DAYS => '7',
            self::BOLETO_DISCOUNT => '0',
            self::CHECKOUT_PB_ENABLED => '1',
            self::CHECKOUT_PB_TITLE => 'Pagar no Checkout PB',
            self::CHECKOUT_PB_DESCRIPTION => 'Finalize o pagamento na página segura do Checkout PB',
            self::CHECKOUT_PB_METHODS => 'CREDIT_CARD,PIX,BOLETO',
        ];
    }
}
