{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

{extends file='page.tpl'}

{block name='page_content'}
  <div class="pb-pix-display">
    <h1>{l s='Pay with PIX' d='Modules.Pbconnect.Shop'}</h1>
    <p>{l s='Scan the QR code or copy the code below. Payment confirmation is automatic.' d='Modules.Pbconnect.Shop'}</p>
    {if $pb_pix_image}
      <p class="text-center">
        <img src="{$pb_pix_image|escape:'html':'UTF-8'}" alt="PIX QR" style="max-width:280px;height:auto;">
      </p>
    {/if}
    {if $pb_pix_text}
      <div class="form-group">
        <label>{l s='PIX copy and paste' d='Modules.Pbconnect.Shop'}</label>
        <textarea id="pb-pix-text" class="form-control" rows="4" readonly>{$pb_pix_text|escape:'html':'UTF-8'}</textarea>
        <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('pb-pix-text').value)">
          {l s='Copy code' d='Modules.Pbconnect.Shop'}
        </button>
      </div>
    {/if}
    <p>
      <a class="btn btn-secondary" href="{$pb_confirmation_url|escape:'html':'UTF-8'}">
        {l s='Continue to order confirmation' d='Modules.Pbconnect.Shop'}
      </a>
    </p>
  </div>
{/block}
