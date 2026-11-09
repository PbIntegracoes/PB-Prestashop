<?php
/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace PbConnect\Order;

use PbConnect\Config\ConfigurationKeys;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderMapper
{
    public function buildFromCart(\Cart $cart, string $referenceId): array
    {
        $customer = new \Customer((int) $cart->id_customer);
        $address = new \Address((int) $cart->id_address_invoice);

        $items = [];
        $products = $cart->getProducts();
        $i = 0;
        foreach ($products as $product) {
            ++$i;
            $unitCents = (int) round((float) $product['price_wt'] * 100);
            $items[] = [
                'reference_id' => 'item-' . $i,
                'name' => \Tools::substr($product['name'], 0, 100),
                'quantity' => (int) $product['cart_quantity'],
                'unit_amount' => max(1, $unitCents),
            ];
        }

        if ($items === []) {
            $items[] = [
                'reference_id' => 'cart-total',
                'name' => 'Order total',
                'quantity' => 1,
                'unit_amount' => $this->cartTotalCents($cart),
            ];
        }

        return [
            'reference_id' => $referenceId,
            'customer' => $this->buildCustomer($customer, $address),
            'items' => $items,
        ];
    }

    public function buildFromOrder(\Order $order): array
    {
        $customer = new \Customer((int) $order->id_customer);
        $address = new \Address((int) $order->id_address_invoice);
        $items = [];
        $orderProducts = $order->getProducts();
        $i = 0;
        foreach ($orderProducts as $product) {
            ++$i;
            $items[] = [
                'reference_id' => 'item-' . $i,
                'name' => \Tools::substr($product['product_name'], 0, 100),
                'quantity' => (int) $product['product_quantity'],
                'unit_amount' => max(1, (int) round((float) $product['unit_price_tax_incl'] * 100)),
            ];
        }

        return [
            'reference_id' => (string) $order->id,
            'customer' => $this->buildCustomer($customer, $address),
            'items' => $items,
        ];
    }

    /**
     * PB-shaped address for customer.address / shipping / boleto holder.
     * PrestaShop: address1=street(+number), address2=often bairro in BR shops.
     *
     * @return array{
     *   street: string,
     *   number: string,
     *   complement: string,
     *   locality: string,
     *   city: string,
     *   region_code: string,
     *   country: string,
     *   postal_code: string
     * }
     */
    public function buildCustomerAddress(\Address $address): array
    {
        $state = new \State((int) $address->id_state);
        $rawStreet = trim((string) $address->address1);
        $number = $this->extractStreetNumber($rawStreet);
        $street = $this->streetWithoutNumber($rawStreet, $number);
        if ($street === '') {
            $street = $rawStreet !== '' ? $rawStreet : 'Rua';
        }

        // BR PrestaShop themes often use address2 as bairro (neighborhood).
        $address2 = trim((string) $address->address2);
        $locality = $address2 !== '' ? $address2 : 'Centro';
        $complement = '';

        $postal = preg_replace('/\D/', '', (string) $address->postcode) ?: '';
        $city = trim((string) $address->city);
        $region = strtoupper(trim((string) ($state->iso_code ?? '')));
        if ($region === '' || !preg_match('/^[A-Z]{2}$/', $region)) {
            $region = 'SP';
        }

        return [
            'street' => \Tools::substr($street, 0, 160),
            'number' => $number !== '' ? $number : 'S/N',
            'complement' => \Tools::substr($complement, 0, 40),
            'locality' => \Tools::substr($locality, 0, 60),
            'city' => \Tools::substr($city !== '' ? $city : 'Sao Paulo', 0, 60),
            'region_code' => \Tools::substr($region, 0, 2),
            'country' => 'BRA',
            'postal_code' => strlen($postal) >= 8 ? substr($postal, 0, 8) : ($postal !== '' ? str_pad($postal, 8, '0') : '01001000'),
        ];
    }

    public function cartTotalCents(\Cart $cart): int
    {
        return max(1, (int) round((float) $cart->getOrderTotal(true, \Cart::BOTH) * 100));
    }

    public function orderTotalCents(\Order $order): int
    {
        return max(1, (int) round((float) $order->total_paid_tax_incl * 100));
    }

    public function cartShippingCents(\Cart $cart): int
    {
        return max(0, (int) round((float) $cart->getOrderTotal(true, \Cart::ONLY_SHIPPING) * 100));
    }

    public function getTaxId(\Customer $customer, \Address $address): string
    {
        $dni = preg_replace('/\D/', '', (string) $address->dni);
        if (strlen($dni) >= 11) {
            return $dni;
        }
        $vat = preg_replace('/\D/', '', (string) $address->vat_number);
        if (strlen($vat) >= 11) {
            return $vat;
        }

        return '00000000000';
    }

    public function isPlaceholderTaxId(string $taxId): bool
    {
        $digits = preg_replace('/\D/', '', $taxId) ?: '';

        return $digits === '' || preg_match('/^0+$/', $digits) === 1;
    }

    /** CPF (11) or CNPJ (14) digit lengths — check-digit validation is partner-side. */
    public function looksLikeTaxId(string $taxId): bool
    {
        $digits = preg_replace('/\D/', '', $taxId) ?: '';
        $len = strlen($digits);

        return ($len === 11 || $len === 14) && !$this->isPlaceholderTaxId($digits);
    }

    public function getPhone(\Address $address): array
    {
        $phone = preg_replace('/\D/', '', (string) ($address->phone_mobile ?: $address->phone)) ?: '';
        // Strip country code if the merchant stored +55 / 55…
        if (str_starts_with($phone, '55') && strlen($phone) >= 12) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) < 10) {
            $phone = '11999999999';
        }

        return [
            'country' => '55',
            'area' => substr($phone, 0, 2),
            'number' => substr($phone, 2),
            'type' => 'MOBILE',
        ];
    }

    /**
     * @return array{
     *   name: string,
     *   email: string,
     *   tax_id: string,
     *   phones: list<array>,
     *   address: array<string, string>
     * }
     */
    public function buildCustomer(\Customer $customer, \Address $address): array
    {
        $name = trim($customer->firstname . ' ' . $customer->lastname);
        if ($name === '') {
            $name = 'Customer';
        }

        return [
            'name' => \Tools::substr($name, 0, 100),
            'email' => $customer->email,
            'tax_id' => $this->getTaxId($customer, $address),
            'phones' => [$this->getPhone($address)],
            // Required by Vindi /v1/billing — without it the API fills "Nao Informado".
            'address' => $this->buildCustomerAddress($address),
        ];
    }

    public function getWaitingOrderStateId(): int
    {
        return (int) \Configuration::get(ConfigurationKeys::OS_WAITING) ?: (int) \Configuration::get('PS_OS_BANKWIRE');
    }

    public function getPaidOrderStateId(): int
    {
        return (int) \Configuration::get(ConfigurationKeys::OS_PAID) ?: (int) \Configuration::get('PS_OS_PAYMENT');
    }

    public function getErrorOrderStateId(): int
    {
        return (int) \Configuration::get(ConfigurationKeys::OS_ERROR) ?: (int) \Configuration::get('PS_OS_ERROR');
    }

    private function extractStreetNumber(string $address1): string
    {
        // Prefer trailing "…, 123" / "… 123" / "nº 123"
        if (preg_match('/(?:^|[\s,;nNº°#]+)\s*(\d+[A-Za-z]?)\s*$/u', trim($address1), $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d+[A-Za-z]?)\b/', $address1, $m)) {
            return $m[1];
        }

        return '';
    }

    private function streetWithoutNumber(string $address1, string $number): string
    {
        if ($number === '') {
            return trim($address1);
        }
        $stripped = preg_replace(
            '/[\s,;]*+(?:n[º°o.]?\s*)?' . preg_quote($number, '/') . '\s*$/iu',
            '',
            trim($address1)
        );

        return trim((string) $stripped, " \t,;-");
    }
}
