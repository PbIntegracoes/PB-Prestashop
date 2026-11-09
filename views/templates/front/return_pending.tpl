{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

{extends file='page.tpl'}

{block name='page_content'}
  <div class="pb-return-pending">
    <h1>{l s='Payment processing' d='Modules.Pbconnect.Shop'}</h1>
    <p>{l s='We are confirming your payment. This page will refresh automatically.' d='Modules.Pbconnect.Shop'}</p>
    <script>
      setTimeout(function () { window.location.reload(); }, 4000);
    </script>
  </div>
{/block}
