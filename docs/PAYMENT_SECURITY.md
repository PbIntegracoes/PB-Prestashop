# Payment security (PrestaShop validation checklist)

This module follows the PrestaShop Marketplace payment rules:

1. **Validate `id_cart`** — `CartGuard` checks cart ownership, secure key, addresses, and that no order exists yet.
2. **Do not create the order before the customer starts payment** — gateway APIs are called first (`createAtGateway` / `chargeAtGateway`).
3. **`validateOrder()` amount** — uses `GatewayAmount` extracted from the API v1 response (not `Cart::getOrderTotal()` alone for card / paid flows).
4. **Callback signature / token** — webhooks require an HMAC hash derived from the Connect Key (`CredentialVault::webhookHash`); FO AJAX and validation require `PaymentRequestToken`.

## Flows

| Method | Pattern | Order creation |
|--------|---------|----------------|
| PIX / Boleto | (1) pending after gateway create | After successful `POST /orders`, status waiting |
| Credit card | (2) after approval | Only when gateway status is `PAID` / `AVAILABLE` |
| Checkout PB | (1) pending after checkout create | After successful `POST /checkouts`, status waiting; paid via webhook or admin force-update |

## Validator

Before Addons submission, run the free [PrestaShop Validator](https://validator.prestashop.com/) (seller account required) on a zip of `pb-connect/` renamed to `pbconnect/`.
