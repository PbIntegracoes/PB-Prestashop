{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

<div class="card pb-order-info" style="margin-top:1rem;">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h3 class="card-header-title mb-0">{l s='PB Connect payments' d='Modules.Pbconnect.Admin'}</h3>
    {if !empty($pb_actions)}
      <div class="pb-order-actions" style="display:flex;gap:.5rem;flex-wrap:wrap;">
        {if !empty($pb_actions.vindi_url)}
          <a class="btn btn-sm btn-outline-primary" href="{$pb_actions.vindi_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
            {l s='Ver na Vindi' d='Modules.Pbconnect.Admin'}
          </a>
        {/if}
        {if !empty($pb_actions.yapay_url)}
          <a class="btn btn-sm btn-outline-secondary" href="{$pb_actions.yapay_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
            {l s='Ver na Yapay' d='Modules.Pbconnect.Admin'}
          </a>
        {/if}
        {if !empty($pb_actions.force_update_url)}
          <a
            class="btn btn-sm btn-outline-dark"
            href="{$pb_actions.force_update_url|escape:'html':'UTF-8'}"
            title="{l s='Fetches the order from the API and applies changes if any. Use when webhooks are blocked or unavailable.' d='Modules.Pbconnect.Admin'}"
          >
            {l s='Forçar atualização' d='Modules.Pbconnect.Admin'}
          </a>
        {/if}
      </div>
    {/if}
  </div>
  <div class="card-body">
    {if !empty($pb_force_flash)}
      <div class="alert {if $pb_force_flash.ok}alert-success{else}alert-danger{/if}" role="alert">
        {$pb_force_flash.message|escape:'html':'UTF-8'}
      </div>
    {/if}
    <div class="table-responsive" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table class="table mb-0" style="min-width:640px;">
        <thead>
          <tr>
            <th>{l s='Method' d='Modules.Pbconnect.Admin'}</th>
            <th>{l s='Status' d='Modules.Pbconnect.Admin'}</th>
            <th>{l s='Order ID' d='Modules.Pbconnect.Admin'}</th>
            <th>{l s='Charge ID' d='Modules.Pbconnect.Admin'}</th>
            <th>{l s='Amount (cents)' d='Modules.Pbconnect.Admin'}</th>
            <th>{l s='Date' d='Modules.Pbconnect.Admin'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$pb_transactions item=tx}
            <tr>
              <td>{$tx.payment_method|escape:'html':'UTF-8'}</td>
              <td>{$tx.status|escape:'html':'UTF-8'}</td>
              <td style="word-break:break-all;">{$tx.pb_order_id|escape:'html':'UTF-8'}</td>
              <td>{$tx.pb_charge_id|escape:'html':'UTF-8'}</td>
              <td>{$tx.amount_cents|intval}</td>
              <td style="white-space:nowrap;">{$tx.date_add|escape:'html':'UTF-8'}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    {if !empty($pb_card) && $pb_card.is_card}
      <h4 class="mt-3">{l s='Detalhes do cartão' d='Modules.Pbconnect.Admin'}</h4>
      <dl class="row mb-0">
        {if $pb_card.card_masked}
          <dt class="col-sm-4">{l s='Número do cartão' d='Modules.Pbconnect.Admin'}</dt>
          <dd class="col-sm-8">{$pb_card.card_masked|escape:'html':'UTF-8'}</dd>
        {/if}
        {if $pb_card.card_brand || $pb_card.card_brand_icon}
          <dt class="col-sm-4">{l s='Bandeira' d='Modules.Pbconnect.Admin'}</dt>
          <dd class="col-sm-8">
            {if $pb_card.card_brand_icon}
              <img
                src="{$pb_card.card_brand_icon|escape:'html':'UTF-8'}"
                alt="{$pb_card.card_brand_label|default:$pb_card.card_brand|escape:'html':'UTF-8'}"
                title="{$pb_card.card_brand_label|default:$pb_card.card_brand|escape:'html':'UTF-8'}"
                style="height:28px;width:auto;max-width:48px;vertical-align:middle;object-fit:contain;"
              >
            {elseif $pb_card.card_brand_label}
              {$pb_card.card_brand_label|escape:'html':'UTF-8'}
            {else}
              {$pb_card.card_brand|escape:'html':'UTF-8'}
            {/if}
          </dd>
        {/if}
        {if $pb_card.card_expiration}
          <dt class="col-sm-4">{l s='Validade' d='Modules.Pbconnect.Admin'}</dt>
          <dd class="col-sm-8">{$pb_card.card_expiration|escape:'html':'UTF-8'}</dd>
        {/if}
        {if $pb_card.card_holder}
          <dt class="col-sm-4">{l s='Titular' d='Modules.Pbconnect.Admin'}</dt>
          <dd class="col-sm-8">{$pb_card.card_holder|escape:'html':'UTF-8'}</dd>
        {/if}
        {if $pb_card.installments > 0}
          <dt class="col-sm-4">{l s='Parcelas' d='Modules.Pbconnect.Admin'}</dt>
          <dd class="col-sm-8">{$pb_card.installments|intval}x</dd>
        {/if}
        {if $pb_card.three_ds}
          <dt class="col-sm-4">3DS</dt>
          <dd class="col-sm-8">{$pb_card.three_ds|escape:'html':'UTF-8'}</dd>
        {/if}
      </dl>
    {/if}
  </div>
</div>
