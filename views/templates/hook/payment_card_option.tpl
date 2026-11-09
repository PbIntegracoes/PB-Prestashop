{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}
<form method="post" action="{$pb_card_action|escape:'html':'UTF-8'}" class="pb-card-payment-form" id="pb-card-payment-form">
  {include file='module:pbconnect/views/templates/front/payment_card.tpl'}
</form>
