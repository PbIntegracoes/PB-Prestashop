<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Admin;

use PbConnect\Api\PbClient;
use PbConnect\Config\ConfigurationKeys;
use PbConnect\Config\InstallmentMode;
use PbConnect\Security\CredentialVault;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ConfigFormPresenter
{
    /** API v1 onboarding (CONVD…) — not legacy /connect/ (PagBank CON…). */
    private const CONNECT_KEY_PRODUCTION_URL = 'https://pbintegracoes.com/autorizar/?utm_source=prestashopadmin';

    private const CONNECT_KEY_SANDBOX_URL = 'https://pbintegracoes.com/sandbox/?utm_source=prestashopadmin';

    /** @var \Pbconnect */
    private \Pbconnect $module;

    private \Link $link;

    private int $languageId;

    public function __construct(\Pbconnect $module, \Link $link, int $languageId)
    {
        $this->module = $module;
        $this->link = $link;
        $this->languageId = $languageId;
    }

    public function render(): string
    {
        if (\Tools::isSubmit('validatePbConnectKey')) {
            return $this->processValidateKey() . $this->renderForm();
        }

        if (\Tools::isSubmit('submitPbConnect')) {
            $saveError = $this->processSave();
            $banner = $saveError
                ? $this->module->displayError($saveError)
                : $this->module->displayConfirmation($this->module->transAdmin('Settings saved.'));

            return $banner . $this->renderForm();
        }

        return $this->renderForm();
    }

    private function processSave(): ?string
    {
        $key = \Tools::getValue(ConfigurationKeys::CONNECT_KEY);
        if ($key !== '' && $key !== '********') {
            try {
                PbClient::assertConnectKeyValid((string) $key, $this->module->version);
                CredentialVault::storeConnectKey((string) $key);
            } catch (\Throwable $e) {
                return $this->module->transAdmin('Connect Key was not saved because validation failed: ')
                    . $e->getMessage();
            }
        }

        $minBrl = \Tools::getValue(ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS);
        if ($minBrl !== false && $minBrl !== null && $minBrl !== '') {
            $cents = max(500, (int) round((float) str_replace(',', '.', (string) $minBrl) * 100));
            \Configuration::updateValue(ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS, (string) $cents);
        }

        $mode = \Tools::getValue(ConfigurationKeys::CC_INSTALLMENT_MODE);
        if (is_string($mode) && InstallmentMode::isValid($mode)) {
            \Configuration::updateValue(ConfigurationKeys::CC_INSTALLMENT_MODE, $mode);
        }

        $skip = [
            ConfigurationKeys::CONNECT_KEY,
            ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS,
            ConfigurationKeys::CC_INSTALLMENT_MODE,
        ];

        foreach (ConfigurationKeys::defaults() as $configKey => $default) {
            if (in_array($configKey, $skip, true)) {
                continue;
            }
            $value = \Tools::getValue($configKey);
            if ($value !== false && $value !== null) {
                \Configuration::updateValue($configKey, $value);
            }
        }

        return null;
    }

    private function processValidateKey(): string
    {
        try {
            $info = (new PbClient($this->module->version))->connectInfo();

            return $this->module->displayConfirmation(
                $this->module->transAdmin('Connect Key is valid.') . ' ' . \Tools::safeOutput(json_encode($info))
            );
        } catch (\Throwable $e) {
            return $this->module->displayError($this->module->transAdmin('Connect Key validation failed: ') . $e->getMessage());
        }
    }

    private function renderForm(): string
    {
        $helper = new \HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->table = 'pbconnect';
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->moduleConfigureFormAction();
        $helper->submit_action = 'submitPbConnect';
        $helper->default_form_language = $this->languageId;
        $helper->fields_value = $this->getFieldsValues();

        return $this->renderStoredConnectKeyWarning()
            . $this->module->renderTemplate('module:pbconnect/views/templates/admin/config_assets.tpl', [
                'pb_admin_css' => $this->module->getPathUri() . 'views/css/admin-config.css',
                'pb_admin_js' => $this->module->getPathUri() . 'views/js/admin-config.js',
            ])
            . $helper->generateForm($this->buildFormSections());
    }

    private function renderStoredConnectKeyWarning(): string
    {
        if (!CredentialVault::hasConnectKey()) {
            return '';
        }

        try {
            (new PbClient($this->module->version))->connectInfo();

            return '';
        } catch (\Throwable $e) {
            return $this->module->displayError(
                $this->module->transAdmin('The stored Connect Key is invalid. Paste a new key from PB Integrações and click Save.')
                . ' (' . \Tools::safeOutput($e->getMessage()) . ')'
            );
        }
    }

    private function moduleConfigureFormAction(): string
    {
        $container = SymfonyContainer::getInstance();
        if ($container !== null && $container->has('router')) {
            try {
                $router = $container->get('router');

                return $router->generate(
                    'admin_module_configure_action',
                    ['module_name' => $this->module->name],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            } catch (\Throwable $e) {
            }
        }

        return $this->link->getAdminLink('AdminModules', false, [], [
            'configure' => $this->module->name,
            'tab_module' => $this->module->tab,
            'module_name' => $this->module->name,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFormSections(): array
    {
        $stateOptions = $this->buildOrderStateOptions();

        return [
            $this->formSection($this->module->transAdmin('Connect Key'), 'icon-key', [
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Connect Key'),
                    'name' => ConfigurationKeys::CONNECT_KEY,
                    'id' => 'pbconnect_connect_key',
                    'desc' => $this->module->transAdmin('Paste a CONVD / CONVDSANDBOX key from PB Integrações.'),
                    'size' => 80,
                    'autocomplete' => false,
                ],
                [
                    'type' => 'html',
                    'name' => 'connect_key_actions',
                    'html_content' => $this->buildConnectKeyActionsHtml(),
                ],
            ], false),
            $this->formSection($this->module->transAdmin('General settings'), 'icon-cogs', [
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Debug logs'),
                    'name' => ConfigurationKeys::DEBUG_MODE,
                    'desc' => $this->module->transAdmin(
                        'When enabled, API requests are appended to var/logs/pbintegracoes.log (tokens redacted).'
                    ),
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Prefer short notification URLs'),
                    'name' => ConfigurationKeys::USE_PROXY,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'select',
                    'label' => $this->module->transAdmin('Order state — awaiting payment'),
                    'name' => ConfigurationKeys::OS_WAITING,
                    'options' => ['query' => $stateOptions, 'id' => 'id', 'name' => 'name'],
                ],
                [
                    'type' => 'select',
                    'label' => $this->module->transAdmin('Order state — paid'),
                    'name' => ConfigurationKeys::OS_PAID,
                    'options' => ['query' => $stateOptions, 'id' => 'id', 'name' => 'name'],
                ],
                [
                    'type' => 'select',
                    'label' => $this->module->transAdmin('Order state — payment error'),
                    'name' => ConfigurationKeys::OS_ERROR,
                    'options' => ['query' => $stateOptions, 'id' => 'id', 'name' => 'name'],
                ],
            ], false),
            $this->formSection($this->module->transAdmin('Credit card'), 'icon-credit-card', [
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Credit card enabled'),
                    'name' => ConfigurationKeys::CC_ENABLED,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Credit card title'),
                    'name' => ConfigurationKeys::CC_TITLE,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Installments enabled'),
                    'name' => ConfigurationKeys::CC_INSTALLMENTS,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'select',
                    'label' => $this->module->transAdmin('Installment options'),
                    'name' => ConfigurationKeys::CC_INSTALLMENT_MODE,
                    'options' => [
                        'query' => $this->installmentModeOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Max installments without interest'),
                    'name' => ConfigurationKeys::CC_MAX_NO_INTEREST,
                    'form_group_class' => 'pb-field-merchant-upto-n',
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Minimum installment amount (BRL)'),
                    'name' => ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS,
                    'form_group_class' => 'pb-field-min-installment',
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Limit installments'),
                    'name' => ConfigurationKeys::CC_MAX_INSTALLMENTS,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('3D Secure enabled'),
                    'name' => ConfigurationKeys::CC_3DS_ENABLED,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('3DS minimum amount (cents)'),
                    'name' => ConfigurationKeys::CC_3DS_MIN_CENTS,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Allow continue without 3DS'),
                    'name' => ConfigurationKeys::CC_3DS_ALLOW_CONTINUE,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
            ], false),
            $this->formSection($this->module->transAdmin('PIX'), 'icon-qrcode', [
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('PIX enabled'),
                    'name' => ConfigurationKeys::PIX_ENABLED,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('PIX title'),
                    'name' => ConfigurationKeys::PIX_TITLE,
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('PIX expiration (minutes)'),
                    'name' => ConfigurationKeys::PIX_EXPIRY_MINUTES,
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('PIX discount (%)'),
                    'name' => ConfigurationKeys::PIX_DISCOUNT,
                ],
            ], false),
            $this->formSection($this->module->transAdmin('Boleto'), 'icon-file-text', [
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Boleto enabled'),
                    'name' => ConfigurationKeys::BOLETO_ENABLED,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Boleto title'),
                    'name' => ConfigurationKeys::BOLETO_TITLE,
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Boleto due days'),
                    'name' => ConfigurationKeys::BOLETO_EXPIRY_DAYS,
                ],
            ], false),
            $this->formSection($this->module->transAdmin('Checkout PB'), 'icon-external-link', [
                [
                    'type' => 'switch',
                    'label' => $this->module->transAdmin('Checkout PB enabled'),
                    'name' => ConfigurationKeys::CHECKOUT_PB_ENABLED,
                    'is_bool' => true,
                    'values' => $this->switchValues(),
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Checkout PB title'),
                    'name' => ConfigurationKeys::CHECKOUT_PB_TITLE,
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->transAdmin('Payment methods (comma-separated)'),
                    'name' => ConfigurationKeys::CHECKOUT_PB_METHODS,
                    'desc' => 'CREDIT_CARD,PIX,BOLETO',
                ],
            ], true),
        ];
    }

    /**
     * @param list<array<string, mixed>> $inputs
     */
    private function formSection(string $legend, string $icon, array $inputs, bool $submit): array
    {
        $form = [
            'form' => [
                'legend' => ['title' => $legend, 'icon' => $icon],
                'input' => $inputs,
            ],
        ];
        if ($submit) {
            $form['form']['submit'] = [
                'title' => $this->module->transAdmin('Save'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submitPbConnect',
            ];
        }

        return $form;
    }

    private function buildConnectKeyActionsHtml(): string
    {
        return $this->module->renderTemplate('module:pbconnect/views/templates/admin/connect_key_actions.tpl', [
            'pb_prod_url' => self::CONNECT_KEY_PRODUCTION_URL,
            'pb_sand_url' => self::CONNECT_KEY_SANDBOX_URL,
            'pb_validate_label' => $this->module->transAdmin('Validate key'),
            'pb_prod_label' => $this->module->transAdmin('Generate production key'),
            'pb_sand_label' => $this->module->transAdmin('Generate sandbox key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFieldsValues(): array
    {
        $values = [];
        foreach (ConfigurationKeys::defaults() as $key => $default) {
            $values[$key] = \Configuration::get($key);
        }
        $values[ConfigurationKeys::CONNECT_KEY] = CredentialVault::hasConnectKey() ? '********' : '';

        $minCents = (int) \Configuration::get(ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS);
        $values[ConfigurationKeys::CC_MIN_INSTALLMENT_CENTS] = number_format($minCents / 100, 2, '.', '');

        return $values;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function installmentModeOptions(): array
    {
        return [
            ['id' => InstallmentMode::BUYER_ALWAYS, 'name' => $this->module->transAdmin('Interest paid by buyer (always)')],
            ['id' => InstallmentMode::MERCHANT_UPTO_N, 'name' => $this->module->transAdmin('Up to N installments without interest')],
            ['id' => InstallmentMode::MERCHANT_BY_MIN_INSTALLMENT, 'name' => $this->module->transAdmin('By minimum installment value')],
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function buildOrderStateOptions(): array
    {
        $states = \OrderState::getOrderStates($this->languageId);
        $options = [];
        foreach ($states as $state) {
            $options[] = [
                'id' => (int) $state['id_order_state'],
                'name' => (string) $state['name'],
            ];
        }

        return $options;
    }

    /**
     * @return list<array{id: string, value: int, label: string}>
     */
    private function switchValues(): array
    {
        return [
            ['id' => 'active_on', 'value' => 1, 'label' => $this->module->transAdmin('Yes')],
            ['id' => 'active_off', 'value' => 0, 'label' => $this->module->transAdmin('No')],
        ];
    }
}
