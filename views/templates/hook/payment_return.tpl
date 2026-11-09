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
    <div class="pb-pix-return">
      <h3>{l s='PIX payment' d='Modules.Pbconnect.Shop'}</h3>
      {if $pb_pix.image_url}
        <img src="{$pb_pix.image_url|escape:'html':'UTF-8'}" alt="PIX QR" style="max-width:220px;">
      {/if}
      {if $pb_pix.text}
        <textarea class="form-control" rows="3" readonly>{$pb_pix.text|escape:'html':'UTF-8'}</textarea>
      {/if}
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
