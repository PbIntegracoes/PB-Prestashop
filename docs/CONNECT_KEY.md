# Connect Key (API v1 / Vindi)

PB Connect uses **only** API v1 Connect Keys:

| Environment | Prefix |
|-------------|--------|
| Production | `CONVD…` (e.g. `CONVD30`, `CONVD14`, `CONVDFLX`) |
| Sandbox | `CONVDSANDBOX…` |

Obtain keys at:

- Production: https://pbintegracoes.com/autorizar/?utm_source=prestashopadmin
- Sandbox: https://pbintegracoes.com/sandbox/?utm_source=prestashopadmin

Do **not** use legacy `CON…` / `CONSANDBOX…` keys (without `VD`) — this module only accepts `CONVD…` / `CONVDSANDBOX…` against API v1.

Never call the partner (Vindi) hosts with the Connect Key. The store talks only to `https://api.pbintegracoes.com/v1/`.
