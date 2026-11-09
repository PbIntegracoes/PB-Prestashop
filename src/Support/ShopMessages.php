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

/**
 * Front-office user-facing strings (translatable via Modules.Pbconnect.Shop).
 */
final class ShopMessages
{
    /**
     * Map gateway / API exceptions to a customer-facing message (mirrors Woo humanize).
     */
    public static function fromException(\Pbconnect $module, \Throwable $e): string
    {
        $raw = trim($e->getMessage());
        $hay = function_exists('mb_strtolower') ? mb_strtolower($raw) : strtolower($raw);

        if (str_contains($hay, 'gateway_token')
            || str_contains($hay, 'payment_profile')
            || str_contains($hay, 'card token')
        ) {
            return self::shop(
                $module,
                'We could not charge this card. Please enter the card details again.',
                'Não foi possível cobrar este cartão. Informe os dados novamente.'
            );
        }

        $mentionsTaxId = str_contains($hay, 'registry_code')
            || str_contains($hay, 'tax_id')
            || str_contains($hay, 'cpf/cnpj')
            || str_contains($hay, 'cpf ou cnpj')
            || (str_contains($hay, 'cpf') && str_contains($hay, 'inválid'))
            || (str_contains($hay, 'cnpj') && str_contains($hay, 'inválid'));

        if ($mentionsTaxId) {
            $blank = str_contains($hay, 'não pode ficar em branco')
                || str_contains($hay, 'nao pode ficar em branco')
                || str_contains($hay, 'cannot be blank')
                || str_contains($hay, 'cannot be empty')
                || str_contains($hay, 'obrigatório')
                || str_contains($hay, 'obrigatorio')
                || str_contains($hay, 'missing');
            if ($blank || str_contains($hay, '00000000000')) {
                return self::invalidTaxIdMissing($module);
            }

            return self::invalidTaxId($module);
        }

        if (str_contains($hay, 'declined') || str_contains($hay, 'not approved')) {
            return self::cardNotApproved($module, []);
        }

        // Drop technical upstream suffix: "(POST /v1/billing → 422)"
        $clean = trim((string) preg_replace(
            '/\s*\((?:GET|POST|PUT|PATCH|DELETE)\s+\/[^)]+\)\s*$/u',
            '',
            $raw
        ));
        if ($clean !== ''
            && !preg_match('/\b(UPSTREAM_ERROR|invalid_parameter|HTTP\s*\d{3})\b/i', $clean)
            && !preg_match('/\b(registry_code|gateway_token|payment_profile_id)\b/i', $clean)
        ) {
            return $clean;
        }

        return self::shop(
            $module,
            'We could not process the payment. Please try again in a moment.',
            'Não foi possível processar o pagamento. Tente novamente em instantes.'
        );
    }

    public static function invalidTaxId(\Pbconnect $module): string
    {
        return self::shop(
            $module,
            'Invalid CPF or CNPJ. Please update your billing address with a valid document and try again.',
            'CPF ou CNPJ inválido. Atualize o endereço de cobrança com um documento válido e tente novamente.'
        );
    }

    public static function invalidTaxIdMissing(\Pbconnect $module): string
    {
        return self::shop(
            $module,
            'Please enter a valid CPF or CNPJ in your billing address (DNI / VAT number) to continue.',
            'Informe um CPF ou CNPJ válido no endereço de cobrança (DNI / Nº de IVA) para continuar.'
        );
    }

    /**
     * Friendly message when card charge is not PAID/AVAILABLE.
     *
     * @param array<string, mixed> $response
     */
    public static function cardNotApproved(\Pbconnect $module, array $response): string
    {
        $status = strtoupper((string) ($response['status'] ?? $response['charges'][0]['status'] ?? ''));
        $reason = strtoupper((string) ($response['decline_reason'] ?? ''));

        if ($reason === 'INTERNAL') {
            return self::shop(
                $module,
                'We could not complete this payment for security reasons. Please try another card or payment method.',
                'Não foi possível concluir o pagamento por motivos de segurança. Tente outro cartão ou método de pagamento.'
            );
        }

        if ($status === 'CANCELED' || $reason === 'CANCELED') {
            return self::shop(
                $module,
                'The payment was canceled. Please try again.',
                'O pagamento foi cancelado. Tente novamente.'
            );
        }

        if ($status === 'FAILED' || $reason === 'FAILED') {
            return self::shop(
                $module,
                'We could not process the payment. Please try again in a moment.',
                'Não foi possível processar o pagamento. Tente novamente em instantes.'
            );
        }

        return self::shop(
            $module,
            'Your card payment was not approved. Please check the details or try another card or payment method.',
            'Seu pagamento com cartão não foi aprovado. Verifique os dados ou use outro cartão ou método de pagamento.'
        );
    }

    /**
     * Prefer language.locale (pt-BR). Iso "pt" catalogues are often empty for modules.
     */
    private static function shop(\Pbconnect $module, string $english, string $portuguese): string
    {
        $language = \Context::getContext()->language;
        $locale = is_object($language) && !empty($language->locale)
            ? (string) $language->locale
            : null;
        $iso = is_object($language) ? strtolower((string) $language->iso_code) : '';

        $translated = $module->transShop($english, [], $locale);
        if ($translated !== $english) {
            return $translated;
        }

        if ($locale !== 'pt-BR') {
            $translatedBr = $module->transShop($english, [], 'pt-BR');
            if ($translatedBr !== $english) {
                return $translatedBr;
            }
        }

        if ($iso === 'pt' || ($locale !== null && str_starts_with(strtolower($locale), 'pt'))) {
            return $portuguese;
        }

        return $english;
    }
}
