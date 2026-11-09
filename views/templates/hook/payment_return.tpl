{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

<div class="pb-payment-return">
  <p>{l s='Your order %reference% was placed successfully.' sprintf=['%reference%' => $order_reference] d='Modules.Pbconnect.Shop'}</p>

  {if $pb_pix}
    <div
      class="pb-pix-success"
      id="pbconnect-pix-success"
      data-order-id="{$pb_pix_order_id|intval}"
      data-status-url="{$pb_pix_status_url|escape:'html':'UTF-8'}"
      data-poll-interval="3000"
      data-poll-timeout="600000"
      data-label-waiting="{l s='Awaiting your payment' d='Modules.Pbconnect.Shop'}"
      data-label-confirmed="{l s='Payment confirmed' d='Modules.Pbconnect.Shop'}"
      data-label-copied="{l s='Copied ✔' d='Modules.Pbconnect.Shop'}"
    >
      <div class="pb-pix-details"{if $pb_pix_paid} style="display: none;"{/if}>
        <h3>{l s='Pay with PIX' d='Modules.Pbconnect.Shop'}</h3>
        <p>{l s='Scan the code below with your bank app.' d='Modules.Pbconnect.Shop'}</p>

        {if $pb_pix.image_url}
          <div class="pb-pix-qr-container">
            <img
              src="{$pb_pix.image_url|escape:'html':'UTF-8'}"
              class="pb-pix-qr"
              alt="{l s='PIX QR Code' d='Modules.Pbconnect.Shop'}"
              width="200"
              height="200"
            >
          </div>
        {/if}

        {if $pb_pix.text}
          <p>{l s='Or copy and paste the code below in your bank app using PIX Copy and Paste.' d='Modules.Pbconnect.Shop'}</p>
          <div class="pb-pix-code-container">
            <label class="pb-pix-code-label" for="pbconnect-pix-code">{l s='PIX code' d='Modules.Pbconnect.Shop'}</label>
            <div class="pb-pix-code-row">
              <input
                type="text"
                id="pbconnect-pix-code"
                class="pb-pix-code form-control"
                value="{$pb_pix.text|escape:'html':'UTF-8'}"
                readonly="readonly"
              >
              <button type="button" class="btn btn-primary pb-pix-copy-btn" title="{l s='Copy' d='Modules.Pbconnect.Shop'}">
                {if $pb_pix_copy_icon}
                  <img src="{$pb_pix_copy_icon|escape:'html':'UTF-8'}" alt="" width="20" height="20" aria-hidden="true">
                {/if}
                <span class="pb-pix-copy-label">{l s='Copy' d='Modules.Pbconnect.Shop'}</span>
              </button>
            </div>
            <p class="pb-pix-copied" hidden>{l s='Copied ✔' d='Modules.Pbconnect.Shop'}</p>
          </div>
        {/if}

        {if $pb_pix_expires_at}
          <p class="pb-pix-expiration">
            <strong>{l s='This PIX code expires on %date%.' sprintf=['%date%' => $pb_pix_expires_at] d='Modules.Pbconnect.Shop'}</strong>
          </p>
        {/if}
      </div>

      <div
        class="pb-pix-payment-status {if $pb_pix_paid}is-confirmed{else}is-waiting{/if}"
        role="status"
        aria-live="polite"
      >
        <span class="pb-pix-status-dot" aria-hidden="true"></span>
        <span class="pb-pix-status-text">
          {if $pb_pix_paid}
            {l s='Payment confirmed' d='Modules.Pbconnect.Shop'}
          {else}
            {l s='Awaiting your payment' d='Modules.Pbconnect.Shop'}
          {/if}
        </span>
      </div>
    </div>
  {/if}

  {if $pb_boleto}
    <div class="pb-boleto-return">
      <h3>{l s='Boleto' d='Modules.Pbconnect.Shop'}</h3>
      {if $pb_boleto.digitable_line}
        <p>{$pb_boleto.digitable_line|escape:'html':'UTF-8'}</p>
      {/if}
      {if $pb_boleto.hosted_url}
        <p><a href="{$pb_boleto.hosted_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Open boleto' d='Modules.Pbconnect.Shop'}</a></p>
      {/if}
    </div>
  {/if}
</div>
