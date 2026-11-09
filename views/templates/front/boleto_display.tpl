{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

{extends file='page.tpl'}

{block name='page_content'}
  <div class="pb-boleto-display">
    <h1>{l s='Boleto generated' d='Modules.Pbconnect.Shop'}</h1>
    {if $pb_boleto.digitable_line}
      <p><strong>{l s='Digitable line' d='Modules.Pbconnect.Shop'}:</strong> {$pb_boleto.digitable_line|escape:'html':'UTF-8'}</p>
    {/if}
    {if $pb_boleto.barcode}
      <p><strong>{l s='Barcode' d='Modules.Pbconnect.Shop'}:</strong> {$pb_boleto.barcode|escape:'html':'UTF-8'}</p>
    {/if}
    <p>
      <a class="btn btn-primary" href="{$pb_confirmation_url|escape:'html':'UTF-8'}">
        {l s='Continue to order confirmation' d='Modules.Pbconnect.Shop'}
      </a>
    </p>
  </div>
{/block}
