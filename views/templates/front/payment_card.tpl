{**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 *}

{* PB Connect credit card form — card fields first; installments last (still prefetched) *}
<div class="pb-card-form" id="pb-card-form">
  <div class="form-group">
    <label for="pb-card-holder">{l s='Card holder' d='Modules.Pbconnect.Shop'}</label>
    <input
      type="text"
      id="pb-card-holder"
      class="form-control"
      autocomplete="cc-name"
      autocapitalize="characters"
      maxlength="64"
      spellcheck="false"
      required
    >
  </div>
  <div class="form-group pb-card-number-group">
    <label for="pb-card-number">{l s='Card number' d='Modules.Pbconnect.Shop'}</label>
    <div class="pb-field-icon-wrap" id="pb-card-number-wrap" data-pb-brand="">
      <input
        type="text"
        id="pb-card-number"
        class="form-control"
        inputmode="numeric"
        autocomplete="cc-number"
        maxlength="23"
        placeholder="•••• •••• •••• ••••"
        spellcheck="false"
        required
      >
      <span class="pb-card-brands" aria-hidden="true" title="Visa, Mastercard, Amex, Elo, Hipercard, Diners, JCB">
        <img class="pb-brand pb-brand-visa" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/visa.svg" alt="">
        <img class="pb-brand pb-brand-mastercard" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/mastercard.svg" alt="">
        <img class="pb-brand pb-brand-amex" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/amex.svg" alt="">
        <img class="pb-brand pb-brand-elo" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/elo.svg" alt="">
        <img class="pb-brand pb-brand-hipercard" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/hipercard.svg" alt="">
        <img class="pb-brand pb-brand-diners" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/diners.svg" alt="">
        <img class="pb-brand pb-brand-jcb" src="{$pb_img_url|escape:'html':'UTF-8'}credit-cards/jcb.svg" alt="">
        <span class="pb-brand-more">+3</span>
      </span>
    </div>
  </div>
  <div class="row">
    <div class="col-xs-6 form-group">
      <label for="pb-card-expiry">{l s='Expiry (MM/YY)' d='Modules.Pbconnect.Shop'}</label>
      <input
        type="text"
        id="pb-card-expiry"
        class="form-control"
        inputmode="numeric"
        autocomplete="cc-exp"
        maxlength="5"
        placeholder="MM/AA"
        spellcheck="false"
        required
      >
    </div>
    <div class="col-xs-6 form-group">
      <label for="pb-card-cvv">{l s='CVV' d='Modules.Pbconnect.Shop'}</label>
      <div class="pb-field-icon-wrap pb-field-cvv-wrap">
        <input
          type="text"
          id="pb-card-cvv"
          class="form-control"
          inputmode="numeric"
          autocomplete="cc-csc"
          maxlength="4"
          placeholder="•••"
          spellcheck="false"
          required
        >
      </div>
    </div>
  </div>
  <div class="form-group" id="pb-installments-wrap">
    <label for="pb-card-installments">{l s='Installments' d='Modules.Pbconnect.Shop'}</label>
    <select id="pb-card-installments" name="pb_installments" class="form-control" aria-busy="true">
      <option value="1">{l s='Loading installments…' d='Modules.Pbconnect.Shop'}</option>
    </select>
  </div>
  <div id="pb-card-error" class="alert alert-danger" style="display:none;"></div>
  <div id="pb-3ds-challenge" style="display:none;"></div>

  <input type="hidden" name="pb_gateway_token" id="pb-gateway-token" value="">
  <input type="hidden" name="pb_payment_company_code" id="pb-payment-company-code" value="">
  <input type="hidden" name="pb_amount_cents" id="pb-amount-cents" value="">
  <input type="hidden" name="pb_payment_profile_id" id="pb-payment-profile-id" value="">
  <input type="hidden" name="pb_three_ds" id="pb-three-ds" value="">
  <input type="hidden" name="pb_card_holder" id="pb-card-holder-post" value="">
  <input type="hidden" name="pb_card_expiry" id="pb-card-expiry-post" value="">
  <input type="hidden" name="pb_card_last4" id="pb-card-last4" value="">
  <input type="hidden" name="token" id="pb-payment-token" value="{$pb_payment_token|escape:'html':'UTF-8'}">
  <input type="hidden" name="id_cart" id="pb-id-cart" value="{$pb_id_cart|intval}">
  <input type="hidden" name="key" id="pb-cart-key" value="{$pb_cart_key|escape:'html':'UTF-8'}">
</div>
