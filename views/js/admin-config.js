/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

(function () {
  function toggleInstallmentFields() {
    var select = document.querySelector('[name="PB_CC_INSTALLMENT_MODE"]');
    if (!select) return;
    var mode = select.value;
    document.querySelectorAll('.pb-field-merchant-upto-n').forEach(function (el) {
      el.style.display = mode === 'merchant_upto_n' ? '' : 'none';
    });
    document.querySelectorAll('.pb-field-min-installment').forEach(function (el) {
      el.style.display = mode === 'merchant_by_min_installment' ? '' : 'none';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    toggleInstallmentFields();
    var select = document.querySelector('[name="PB_CC_INSTALLMENT_MODE"]');
    if (select) select.addEventListener('change', toggleInstallmentFields);
  });
})();
