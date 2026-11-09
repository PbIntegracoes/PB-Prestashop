# PB Connect for PrestaShop

**Version 5.0.0** — Payment module for PrestaShop 8.1+ and 9.x integrating [PB Integrações](https://pbintegracoes.com) **API v1** (partner Vindi via Connect Key `CONVD…` / `CONVDSANDBOX…`).

Aligned with the other PB Integrações 5.x storefront integrations (WooCommerce, Magento, etc.).

## Features

- Credit card (`gateway_token`, installments, 3D Secure)
- PIX (QR + copy-paste, SVG proxy, auto confirmation via webhook)
- Boleto
- Checkout PB (hosted payment page)
- Webhooks with cart ID + HMAC hash validation
- Admin order payment details + force update
- Portuguese (Brazil) translations for PrestaShop Addons (`pt-BR` + `pt`)

## Installation

1. Clone or copy this repository into `modules/pbconnect` (folder name must be `pbconnect`)
2. Install via **Modules > Module Manager**
3. Configure **Connect Key** (`CONVD…` or `CONVDSANDBOX…`)
4. Enable payment methods

```bash
cd modules
git clone git@github.com:PbIntegracoes/PB-Prestashop.git pbconnect
```

Runtime autoload uses `autoload.php` (PSR-4 `PbConnect\`). If you run Composer, use **`composer install --no-dev`** only — `require-dev` (php-cs-fixer) pulls Symfony 8 and breaks PrestaShop’s Symfony 6.4 (`getSubscribedEvents(): array` compile error). Never deploy `vendor/` built with `--dev`.

## Connect Key

See [docs/CONNECT_KEY.md](docs/CONNECT_KEY.md).

## API contract

- Base: `https://api.pbintegracoes.com/v1/`
- Auth: `Authorization: Bearer {CONVD…}`
- Amounts in **centavos**
- `POST /orders` success = **HTTP 201**
- Card: `gateway_token` (never PAN on the store server)
- 3DS: explicit `type: CREDIT_CARD_3DS`

## Marketplace / Validator

Before considering the module ready to distribute:

1. Zip the module folder as `pbconnect/` (technical name) and run the free [PrestaShop Validator](https://validator.prestashop.com/) (seller account login required).
2. Fix every automatic rejection from the validator.
3. Ensure payment checklist rules in [docs/PAYMENT_SECURITY.md](docs/PAYMENT_SECURITY.md).
4. Ensure `logo.png` (32×32) is present.
5. Keep `translations/pt-BR/` complete so the module is listed under Portuguese (Brazil).

## License

MIT
