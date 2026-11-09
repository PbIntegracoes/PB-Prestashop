/**
 * PB Connect — PrestaShop payment module (API v1).
 *
 * @author    PB Integrações
 * @copyright since 2026 PB Integrações
 * @license   https://opensource.org/licenses/MIT MIT
 */

/**
 * PB Connect — Vindi tokenize + installments + 3DS (API v1)
 */
(function () {
  'use strict';

  function cfg() {
    return window.pbConnectConfig || {};
  }

  function digitsOnly(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function detectCompany(cardNumber) {
    var number = digitsOnly(cardNumber);
    if (!number) return '';
    var elo = new RegExp(
      '^((451416)|(509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|' +
      '(457393)|(431274)|(50990[0-2])|(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|' +
      '(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|' +
      '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|' +
      '(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|' +
      '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|' +
      '(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|' +
      '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|' +
      '(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|' +
      '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))\\d*$'
    );
    var hipercard = /^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\d*$/;
    var amex = /^3[47]\d*$/;
    var diners = /^(3(0[0-5]|095|6|[8-9]))\d*$/;
    var jcb = /^(?:2131|1800|35\d{3})\d*$/;
    var mastercard = /^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)\d*$/;
    var visa = /^4\d*$/;
    if (elo.test(number)) return 'elo';
    if (hipercard.test(number)) return 'hipercard';
    if (amex.test(number)) return 'american_express';
    if (diners.test(number)) return 'diners_club';
    if (jcb.test(number)) return 'jcb';
    if (mastercard.test(number)) return 'mastercard';
    if (visa.test(number)) return 'visa';
    return '';
  }

  function brandLabel(code) {
    switch (code) {
      case 'visa': return 'Visa';
      case 'mastercard': return 'Mastercard';
      case 'elo': return 'Elo';
      case 'american_express': return 'Amex';
      case 'hipercard': return 'Hipercard';
      case 'diners_club': return 'Diners';
      case 'jcb': return 'JCB';
      default: return '';
    }
  }

  function brandCssClass(code) {
    switch (code) {
      case 'visa': return 'is-visa';
      case 'mastercard': return 'is-mastercard';
      case 'elo': return 'is-elo';
      case 'american_express': return 'is-amex';
      case 'hipercard': return 'is-hipercard';
      case 'diners_club': return 'is-diners';
      case 'jcb': return 'is-jcb';
      default: return '';
    }
  }

  function maxPanLength(company) {
    if (company === 'american_express') return 15;
    if (company === 'diners_club') return 14;
    return 16;
  }

  function maxCvvLength(company) {
    return company === 'american_express' ? 4 : 3;
  }

  function formatPan(digits, company) {
    var max = maxPanLength(company);
    var d = digitsOnly(digits).slice(0, max);
    if (company === 'american_express') {
      return d.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*$/, function (_, a, b, c) {
        return [a, b, c].filter(Boolean).join(' ');
      });
    }
    return d.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
  }

  function formatExpiry(raw) {
    var d = digitsOnly(raw).slice(0, 4);
    if (d.length === 0) return '';
    if (d.length === 1) {
      // 2–9 cannot start a valid month → force 0N/
      var first = parseInt(d, 10);
      if (first >= 2) {
        return '0' + d + '/';
      }
      return d;
    }
    var month = d.slice(0, 2);
    var year = d.slice(2);
    var mNum = parseInt(month, 10);
    if (mNum > 12) {
      // typed e.g. 13 → treat first digit as month 01..09 and push rest to year
      month = '0' + d.charAt(0);
      year = d.slice(1, 3);
    } else if (month === '00') {
      month = '01';
    }
    return year ? month + '/' + year : month + (d.length >= 2 ? '/' : '');
  }

  function normalizeExpiryOnBlur(raw) {
    var d = digitsOnly(raw);
    if (!d) return '';
    var month;
    var year;
    if (d.length === 1) {
      month = ('0' + d).slice(-2);
      year = '';
    } else if (d.length === 2) {
      month = d;
      year = '';
    } else if (d.length === 3) {
      month = ('0' + d.charAt(0)).slice(-2);
      year = d.slice(1);
    } else {
      month = d.slice(0, 2);
      year = d.slice(2, 4);
    }
    var mNum = parseInt(month, 10);
    if (!mNum || mNum < 1) month = '01';
    if (mNum > 12) month = '12';
    month = ('0' + month).slice(-2);
    return year ? month + '/' + year : month;
  }

  function normalizeHolderName(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Za-z\s]/g, '')
      .replace(/\s+/g, ' ')
      .trim()
      .toUpperCase();
  }

  function formatMoney(cents) {
    var value = (Number(cents) || 0) / 100;
    try {
      return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    } catch (e) {
      return 'R$ ' + value.toFixed(2).replace('.', ',');
    }
  }

  function updateBrandUi(company) {
    var number = document.getElementById('pb-card-number');
    var wrap = document.getElementById('pb-card-number-wrap');
    var cvv = document.getElementById('pb-card-cvv');
    if (cvv) {
      cvv.maxLength = maxCvvLength(company);
      cvv.placeholder = company === 'american_express' ? '••••' : '•••';
    }
    if (wrap) {
      wrap.setAttribute('data-pb-brand', company || '');
    }
    if (!number) return;
    var brandClasses = [
      'is-visa', 'is-mastercard', 'is-elo', 'is-amex',
      'is-hipercard', 'is-diners', 'is-jcb'
    ];
    brandClasses.forEach(function (cls) {
      number.classList.remove(cls);
    });
    var css = brandCssClass(company);
    if (css) {
      number.classList.add(css);
      number.setAttribute('data-pb-brand', brandLabel(company));
    } else {
      number.removeAttribute('data-pb-brand');
    }
  }

  var lastFeesBin = '';
  // Prefetch with a generic Mastercard BIN (Woo uses the same idea) so installments show before PAN.
  var PREFETCH_BIN = '555555';

  function onCardFieldInput(ev) {
    var el = ev.target;
    if (!el || !el.id) return;

    if (el.id === 'pb-card-holder') {
      var start = el.selectionStart;
      var before = el.value;
      var cleaned = before
        .replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, '')
        .replace(/\s{2,}/g, ' ')
        .toUpperCase();
      if (cleaned !== before) {
        el.value = cleaned;
        if (typeof start === 'number') {
          el.setSelectionRange(Math.min(start, cleaned.length), Math.min(start, cleaned.length));
        }
      }
      return;
    }

    if (el.id === 'pb-card-number') {
      var company = detectCompany(el.value);
      el.value = formatPan(el.value, company);
      updateBrandUi(company);
      var bin = digitsOnly(el.value).slice(0, 6);
      if (bin.length >= 6 && bin !== lastFeesBin) {
        loadFees(bin, company);
      }
      return;
    }

    if (el.id === 'pb-card-expiry') {
      el.value = formatExpiry(el.value);
      return;
    }

    if (el.id === 'pb-card-cvv') {
      var numberEl = document.getElementById('pb-card-number');
      var brand = detectCompany(numberEl && numberEl.value);
      el.value = digitsOnly(el.value).slice(0, maxCvvLength(brand));
    }
  }

  function onCardFieldBlur(ev) {
    var el = ev.target;
    if (!el || !el.id) return;

    if (el.id === 'pb-card-holder') {
      el.value = normalizeHolderName(el.value);
      return;
    }

    if (el.id === 'pb-card-number') {
      var company = detectCompany(el.value);
      el.value = formatPan(el.value, company || 'mastercard');
      updateBrandUi(detectCompany(el.value));
      return;
    }

    if (el.id === 'pb-card-expiry') {
      el.value = normalizeExpiryOnBlur(el.value);
    }
  }

  function showError(msg) {
    var el = document.getElementById('pb-card-error');
    if (!el) return;
    el.style.display = msg ? 'block' : 'none';
    el.textContent = msg || '';
  }

  function processUrl(action) {
    var base = cfg().processUrl || '';
    var sep = base.indexOf('?') >= 0 ? '&' : '?';
    return base + sep + 'action=' + encodeURIComponent(action);
  }

  function formatAjaxError(body, status) {
    if (!body || typeof body !== 'object') {
      return 'HTTP ' + status;
    }
    if (body.error && body.error !== 'UPSTREAM_ERROR' && body.error !== '3DS upstream error') {
      // Module process.php puts the human message in `error`.
      if (!body.message || body.message === body.error) {
        return String(body.error);
      }
    }
    var parts = [];
    if (body.message) parts.push(String(body.message));
    else if (body.error) parts.push(String(body.error));
    var up = body.upstream && body.upstream.body;
    if (up && Array.isArray(up.errors) && up.errors[0]) {
      var e0 = up.errors[0];
      var detail = (e0.message || e0.code || '') + (e0.details ? (': ' + e0.details) : '');
      if (detail) parts.push(detail);
    }
    return parts.join(' — ') || ('HTTP ' + status);
  }

  function fetchJson(action, options) {
    options = options || {};
    return fetch(processUrl(action), Object.assign({
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' }
    }, options)).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) throw new Error(formatAjaxError(body, r.status));
        return body;
      });
    });
  }

  var tokenizeCache = null;

  function loadTokenizeConfig() {
    if (tokenizeCache) return Promise.resolve(tokenizeCache);
    return fetchJson('tokenize_config').then(function (data) {
      tokenizeCache = data;
      return data;
    });
  }

  function tokenizeCard() {
    var holderEl = document.getElementById('pb-card-holder');
    var numberEl = document.getElementById('pb-card-number');
    var expiryEl = document.getElementById('pb-card-expiry');
    var cvvEl = document.getElementById('pb-card-cvv');

    var holder = normalizeHolderName(holderEl && holderEl.value);
    if (holderEl) holderEl.value = holder;
    if (holder.length < 2) {
      throw new Error('Informe o nome impresso no cartão.');
    }

    var number = digitsOnly(numberEl && numberEl.value);
    var company = detectCompany(number) || 'mastercard';
    if (numberEl) numberEl.value = formatPan(number, company);
    updateBrandUi(company);
    if (number.length < 13) {
      throw new Error('Número do cartão inválido.');
    }

    if (expiryEl) expiryEl.value = normalizeExpiryOnBlur(expiryEl.value);
    var expiryRaw = (expiryEl && expiryEl.value) || '';
    var parts = expiryRaw.split('/');
    var expMonth = ('0' + digitsOnly(parts[0] || '')).slice(-2);
    var expYear = digitsOnly(parts[1] || '').slice(-2);
    if (!/^(0[1-9]|1[0-2])$/.test(expMonth) || !/^\d{2}$/.test(expYear)) {
      throw new Error('Validade inválida. Use MM/AA.');
    }

    var cvv = digitsOnly(cvvEl && cvvEl.value).slice(0, maxCvvLength(company));
    if (cvvEl) cvvEl.value = cvv;
    if (cvv.length < maxCvvLength(company)) {
      throw new Error('CVV inválido.');
    }

    var cardExpiration = expMonth + '/20' + expYear;

    return loadTokenizeConfig().then(function (tok) {
      // PB API v1: { public_api_key, payment_profiles_url } (Woo uses the same fields).
      var publicKey = tok.public_api_key || tok.public_key || tok.api_key || '';
      var url = tok.payment_profiles_url || tok.url || '';
      if (!publicKey || !url) throw new Error('Configuração de tokenização indisponível.');
      var auth = btoa(String(publicKey) + ':');
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: 'Basic ' + auth
        },
        body: JSON.stringify({
          holder_name: holder,
          card_expiration: cardExpiration,
          card_number: number,
          card_cvv: cvv,
          payment_method_code: 'credit_card',
          payment_company_code: company
        })
      }).then(function (r) {
        return r.json().then(function (body) {
          if (!r.ok) {
            var detail = '';
            if (body && Array.isArray(body.errors)) {
              detail = body.errors.map(function (e) {
                return (e.parameter ? e.parameter + ': ' : '') + (e.message || '');
              }).join('\n');
            }
            throw new Error('Erro ao tokenizar o cartão.' + (detail ? '\n' + detail : ''));
          }
          var profile = body.payment_profile || body;
          var token = (profile && (profile.gateway_token || profile.token)) || body.gateway_token || '';
          if (!token) throw new Error('Token ausente na resposta.');
          return { gateway_token: token, payment_company_code: company, pan: number };
        });
      });
    });
  }

  function selectedAmountCents() {
    var select = document.getElementById('pb-card-installments');
    var opt = select && select.options[select.selectedIndex];
    if (opt && opt.dataset && opt.dataset.amount) {
      var n = parseInt(opt.dataset.amount, 10);
      if (n >= 100) return n;
    }
    return parseInt(cfg().cartTotalCents || 0, 10) || 0;
  }

  function extractInstallmentPlans(data, company) {
    var pm = data && data.payment_methods;
    if (!pm) return [];

    if (Array.isArray(pm)) {
      var ccArr = pm.find(function (p) { return String(p.type || '').toUpperCase() === 'CREDIT_CARD'; });
      return (ccArr && ccArr.installment_plans) || [];
    }

    var credit = pm.credit_card || data.credit_card || {};
    if (Array.isArray(credit.installment_plans) && credit.installment_plans.length) {
      return credit.installment_plans;
    }

    var brandKey = company || '';
    if (brandKey && credit[brandKey] && Array.isArray(credit[brandKey].installment_plans)) {
      return credit[brandKey].installment_plans;
    }

    var keys = Object.keys(credit);
    for (var i = 0; i < keys.length; i++) {
      var node = credit[keys[i]];
      if (node && Array.isArray(node.installment_plans) && node.installment_plans.length) {
        return node.installment_plans;
      }
    }

    return data.installment_plans || [];
  }

  function installmentOptionLabel(plan) {
    var inst = plan.installments || plan.quantity || 1;
    var total = plan.amount && (plan.amount.value != null ? plan.amount.value : plan.amount);
    if (typeof total === 'object' && total) total = total.value;
    total = parseInt(total, 10);
    if (!(total > 0)) total = parseInt(cfg().cartTotalCents || 0, 10) || 0;

    var installmentValue = plan.installment_value != null
      ? parseInt(plan.installment_value, 10)
      : (inst > 0 ? Math.round(total / inst) : total);

    if (inst <= 1) {
      return '1x de ' + formatMoney(total);
    }

    var interest = plan.interest_free ? ' sem juros' : '';
    return inst + 'x de ' + formatMoney(installmentValue) +
      ' (total ' + formatMoney(total) + ')' + interest;
  }

  function loadFees(bin, company) {
    if (!cfg().installmentsEnabled) {
      var wrapOff = document.getElementById('pb-installments-wrap');
      if (wrapOff) wrapOff.style.display = 'none';
      return Promise.resolve();
    }
    var bin6 = String(bin || PREFETCH_BIN).replace(/\D/g, '').slice(0, 6) || PREFETCH_BIN;
    var url = processUrl('fees') + '&bin=' + encodeURIComponent(bin6);
    var select = document.getElementById('pb-card-installments');
    var wrap = document.getElementById('pb-installments-wrap');
    var previouslySelected = select ? String(select.value || '') : '';
    if (select) select.setAttribute('aria-busy', 'true');
    if (wrap) wrap.style.display = 'block';

    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var plans = extractInstallmentPlans(data, company);
        if (!select) return;
        select.innerHTML = '';
        if (!plans.length) {
          var fallbackTotal = parseInt(cfg().cartTotalCents || 0, 10) || 0;
          var o = document.createElement('option');
          o.value = '1';
          o.dataset.amount = String(fallbackTotal);
          o.textContent = '1x de ' + formatMoney(fallbackTotal);
          select.appendChild(o);
        } else {
          plans.forEach(function (plan) {
            var inst = plan.installments || plan.quantity || 1;
            var total = plan.amount && (plan.amount.value != null ? plan.amount.value : plan.amount);
            if (typeof total === 'object' && total) total = total.value;
            var o = document.createElement('option');
            o.value = String(inst);
            o.dataset.amount = String(total || cfg().cartTotalCents || '');
            o.textContent = installmentOptionLabel(plan);
            select.appendChild(o);
          });
        }
        if (previouslySelected && select.querySelector('option[value="' + previouslySelected + '"]')) {
          select.value = previouslySelected;
        }
        select.removeAttribute('aria-busy');
        lastFeesBin = bin6;
      })
      .catch(function () {
        if (!select) return;
        if (!select.options.length) {
          var total = parseInt(cfg().cartTotalCents || 0, 10) || 0;
          var opt = document.createElement('option');
          opt.value = '1';
          opt.dataset.amount = String(total);
          opt.textContent = '1x de ' + formatMoney(total);
          select.appendChild(opt);
        }
        select.removeAttribute('aria-busy');
      });
  }

  function prefetchInstallments() {
    if (!cfg().installmentsEnabled) return;
    if (document.getElementById('pb-card-installments')) {
      loadFees(PREFETCH_BIN, 'mastercard');
    }
  }

  function deviceInfo() {
    return {
      screen_height: window.screen.height || 1080,
      screen_width: window.screen.width || 1920,
      color_depth: window.screen.colorDepth || 24,
      timezone_offset: new Date().getTimezoneOffset(),
      language: navigator.language || 'pt-BR',
      java_enabled: false,
      user_agent: navigator.userAgent
    };
  }

  function runDeviceDataCollection(setup) {
    return new Promise(function (resolve, reject) {
      var url = setup.device_data_collection_url || setup.CollectionReferenceId || '';
      var jwt = setup.access_token || setup.JWT || '';
      if (!url || !jwt) {
        resolve();
        return;
      }

      // PDF: enroll only after Status === true (no fixed delay).
      // Sample listens on MPI; Cruise Collect often postMessages from Cardinal centinelapi too (Woo).
      var sandbox = !!(cfg().sandbox);
      var mpiOrigin = sandbox
        ? 'https://sandbox-mpi.vindi.com.br'
        : 'https://mpi.vindi.com.br';
      var allowedOrigins = [
        mpiOrigin,
        'https://sandbox-mpi.vindi.com.br',
        'https://mpi.vindi.com.br',
        'https://centinelapistag.cardinalcommerce.com',
        'https://centinelapi.cardinalcommerce.com'
      ];
      var settled = false;

      function originAllowed(origin) {
        if (allowedOrigins.indexOf(origin) !== -1) return true;
        try {
          var host = new URL(origin).hostname;
          return /(^|\.)vindi\.com\.br$|(^|\.)centinelapi(stag)?\.cardinalcommerce\.com$/i.test(host);
        } catch (e) {
          return false;
        }
      }

      function removeDdcDom() {
        try {
          var f = document.getElementById('pb-ddc-form');
          var i = document.getElementById('pb-ddc-iframe');
          if (f) f.remove();
          if (i) i.remove();
        } catch (e) {}
      }

      function finish(ok, err) {
        if (settled) return;
        settled = true;
        clearTimeout(timeout);
        window.removeEventListener('message', onMessage);
        if (!ok) {
          removeDdcDom();
          reject(err || new Error('Falha na coleta de dados do dispositivo (3DS).'));
          return;
        }
        // Keep iframe after success (Woo) — tearing it down aborts in-flight Cardinal XHRs.
        resolve(true);
      }

      // PDF: wait for Status===true. Soft-continue / early enroll → AUTHENTICATION_SKIPPED.
      var timeout = setTimeout(function () {
        finish(false, new Error(
          'Não foi possível coletar os dados do dispositivo (3DS). '
          + 'Desative AdBlock/bloqueadores nesta página, libere Vindi MPI e Cardinal no CSP, e tente novamente.'
        ));
      }, 15000);

      function onMessage(event) {
        if (!originAllowed(event.origin)) return;
        var data = event.data;
        try {
          if (typeof data === 'string') data = JSON.parse(data);
        } catch (e) {
          return;
        }
        if (!data || typeof data !== 'object') return;
        // Official Vindi PDF: if (data?.Status === true) callEnrollAPI();
        var statusOk = data.Status === true || data.Status === 'true' || data.Status === 'True';
        if (statusOk) {
          finish(true);
          return;
        }
        if (data.MessageType === 'profile.completed' && data.Status === false) {
          finish(false, new Error(
            'Coleta de dados do dispositivo (3DS) incompleta. '
            + 'Desative AdBlock/bloqueadores e tente novamente.'
          ));
        }
      }

      window.addEventListener('message', onMessage);

      removeDdcDom();
      var iframe = document.createElement('iframe');
      iframe.id = 'pb-ddc-iframe';
      iframe.name = 'pb-ddc-iframe';
      iframe.setAttribute('aria-hidden', 'true');
      // 1×1 visible to layout — display:none can block Collect scripts / Notification.
      iframe.style.cssText = 'width:1px;height:1px;border:0;position:absolute;left:0;top:0;opacity:0;pointer-events:none;';
      document.body.appendChild(iframe);

      var form = document.createElement('form');
      form.id = 'pb-ddc-form';
      form.method = 'POST';
      form.target = 'pb-ddc-iframe';
      form.action = url;
      form.style.display = 'none';
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'JWT';
      input.value = jwt;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    });
  }

  function waitChallengeTransactionId() {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () {
        window.removeEventListener('message', onMsg);
        reject(new Error('Timeout na autenticação 3DS.'));
      }, 300000);
      function onMsg(ev) {
        var data = ev.data || {};
        if (data.source !== 'pb-connect-vindi-3ds') return;
        clearTimeout(timer);
        window.removeEventListener('message', onMsg);
        var txid = String(data.TransactionId || '');
        if (!txid) {
          reject(new Error('Challenge 3DS sem TransactionId.'));
          return;
        }
        resolve(txid);
      }
      window.addEventListener('message', onMsg);
    });
  }

  function showChallenge(enroll) {
    var stepUpUrl = enroll.step_up_url || enroll.StepUpUrl || '';
    var accessToken = enroll.access_token || enroll.AccessToken || '';
    var container = document.getElementById('pb-3ds-challenge');
    if (!stepUpUrl || !accessToken || !container) {
      return Promise.reject(new Error('Desafio 3DS incompleto.'));
    }
    container.style.display = 'block';
    container.innerHTML = '';
    var iframe = document.createElement('iframe');
    iframe.name = 'pb_3ds_challenge';
    iframe.style.width = '100%';
    iframe.style.minHeight = '400px';
    iframe.style.border = '1px solid #ccc';
    container.appendChild(iframe);
    var form = document.createElement('form');
    form.method = 'POST';
    form.target = 'pb_3ds_challenge';
    form.action = stepUpUrl;
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'JWT';
    input.value = accessToken;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    form.remove();
    return waitChallengeTransactionId();
  }

  function mapThreeDsAuth(auth) {
    var v = auth || {};
    var info = v.consumer_authentication_information || v.consumerAuthenticationInformation || {};
    return {
      status: v.status || v.authentication_status || 'AUTHENTICATION_SUCCESSFUL',
      liability_shift: !!(v.liability_shift || v.liabilityShift),
      authentication_transaction_id: v.authentication_transaction_id || v.TransactionId || '',
      consumer_authentication_information: {
        eci: info.eci || info.eciRaw || '',
        cavv: info.cavv || '',
        xid: info.xid || info.directoryServerTransactionId || ''
      }
    };
  }

  function cardTypeForValidate(company) {
    if (company === 'american_express') return 'american_express';
    if (company === 'mastercard') return 'mastercard';
    if (company === 'elo') return 'elo';
    if (company === 'hipercard') return 'hipercard';
    if (company === 'diners_club') return 'diners_club';
    if (company === 'jcb') return 'jcb';
    return 'visa';
  }

  function maybeRun3ds(tokenResult, amountCents, installments) {
    var c = cfg();
    if (!c.threeDsEnabled || amountCents < (c.threeDsMinCents || 100)) {
      return Promise.resolve(null);
    }
    return fetchJson('payment_methods_3ds').then(function (pm) {
      var available = false;
      var list = pm.payment_methods || pm.data || pm;
      if (Array.isArray(list)) {
        available = list.some(function (m) {
          return (m.code || m.payment_method_code || '') === 'credit_card_3ds' && m.available !== false;
        });
      } else if (pm.available === true || (pm.credit_card_3ds && pm.credit_card_3ds.available)) {
        available = true;
      }
      if (!available) return null;

      return fetchJson('payment_profile', {
        method: 'POST',
        body: JSON.stringify({
          gateway_token: tokenResult.gateway_token,
          payment_company_code: tokenResult.payment_company_code
        })
      }).then(function (profile) {
        var profileId = profile.payment_profile_id;

        function doSetupDdcEnroll() {
          return fetchJson('3ds_setup', {
            method: 'POST',
            body: JSON.stringify({ payment_profile_id: profileId })
          }).then(function (setupResp) {
            var setup = setupResp.setup || setupResp;
            return runDeviceDataCollection(setup).then(function () {
              return fetchJson('3ds_enroll', {
                method: 'POST',
                body: JSON.stringify({
                  session_id: setup.session_id,
                  amount: amountCents,
                  installments: installments,
                  device_info: deviceInfo()
                })
              });
            });
          }).then(function (enrollResp) {
            var enroll = enrollResp.enroll || enrollResp;
            if (enroll && typeof enroll === 'object' && enroll.enroll && typeof enroll.enroll === 'object') {
              enroll = enroll.enroll;
            }
            return enroll;
          });
        }

        return doSetupDdcEnroll().then(function (enroll) {
          var enrollStatus = String(enroll.status || enroll.Status || '').toUpperCase();
          var enrollReason = String(
            enroll.reason || enroll.skip_reason || enroll.authentication_reason || ''
          ).toUpperCase();
          // Incomplete DDC often yields SKIPPED — one retry with fresh setup/DDC (Woo).
          if (enrollStatus === 'AUTHENTICATION_SKIPPED' && enrollReason !== 'BELOW_MINIMUM_AMOUNT') {
            console.warn('PB Connect 3DS: AUTHENTICATION_SKIPPED — retry setup/DDC/enroll', enroll);
            return doSetupDdcEnroll();
          }
          return enroll;
        }).then(function (enroll) {
          var enrollStatus = String(enroll.status || enroll.Status || '').toUpperCase();
          var challengeRequired = enroll.challenge_required === true
            || enroll.challenge_required === 'true'
            || enrollStatus === 'PENDING_AUTHENTICATION';
          var authOk = enroll.liability_shift === true
            || enrollStatus === 'AUTHENTICATION_SUCCESSFUL'
            || enrollStatus === 'SUCCESSFUL'
            || enrollStatus === 'Y'
            || enrollStatus === 'A';
          var notEnrolled = enroll.card_enrolled === false
            || enroll.veres_enrolled === 'N'
            || enroll.veres_enrolled === 'U'
            || enroll.veres_enrolled === 'B'
            || (enrollStatus === 'AUTHENTICATION_FAILED'
              && enroll.veres_enrolled !== 'Y'
              && enroll.card_enrolled !== true);

          if (enrollStatus === 'AUTHENTICATION_SKIPPED') {
            throw new Error(
              '3D Secure pulado (AUTHENTICATION_SKIPPED'
              + (enroll.reason ? ': ' + enroll.reason : '')
              + '). Desative bloqueadores e tente novamente.'
            );
          }

          if (enrollStatus === 'AUTHENTICATION_FAILED' && !notEnrolled && !challengeRequired) {
            throw new Error('Autenticação 3D Secure falhou. Use outro cartão ou método.');
          }

          if (notEnrolled && !challengeRequired) {
            if (c.threeDsAllowContinue) {
              console.warn('PB Connect 3DS: cartão fora do 3DS — continuando sem 3DS');
              return null;
            }
            throw new Error('Este cartão não tem suporte a 3D Secure. Use outro cartão ou método.');
          }

          // Frictionless: use enroll result — do NOT call /validate (Woo/Vindi docs).
          if (!challengeRequired) {
            if (!authOk) {
              throw new Error(
                'Autenticação 3D Secure não concluída. Status: ' + (enroll.status || 'ausente')
              );
            }
            return {
              payment_profile_id: profileId,
              three_ds: mapThreeDsAuth(enroll)
            };
          }

          if (!(enroll.step_up_url || enroll.StepUpUrl) || !(enroll.access_token || enroll.AccessToken)) {
            throw new Error('Desafio 3DS solicitado sem URL do emissor.');
          }

          return showChallenge(enroll).then(function (txid) {
            if (!txid && c.threeDsAllowContinue) return null;
            if (!txid) throw new Error('3DS TransactionId ausente.');
            return fetchJson('3ds_validate', {
              method: 'POST',
              body: JSON.stringify({
                authentication_transaction_id: txid,
                card_type: cardTypeForValidate(tokenResult.payment_company_code)
              })
            }).then(function (valResp) {
              var auth = valResp.validate || valResp;
              if (auth.validate && typeof auth.validate === 'object') {
                auth = auth.validate;
              }
              var validateStatus = String(auth.status || '').toUpperCase();
              var validateOk = auth.liability_shift === true
                || validateStatus === 'AUTHENTICATION_SUCCESSFUL'
                || validateStatus === 'SUCCESSFUL';
              if (!validateOk) {
                throw new Error('Autenticação 3D Secure falhou. Status: ' + (auth.status || 'desconhecido'));
              }
              return {
                payment_profile_id: profileId,
                three_ds: mapThreeDsAuth(auth)
              };
            });
          });
        });
      });
    }).catch(function (err) {
      if (c.threeDsAllowContinue) {
        console.warn('PB Connect 3DS skipped:', err);
        return null;
      }
      throw err;
    });
  }

  function getActiveCardForm() {
    // Only when the selected payment option is our card form — never fall back to a
    // hidden CC form while PIX/boleto/Checkout PB is selected (that blocked Place Order).
    var checked = document.querySelector('input[name="payment-option"]:checked');
    if (!checked || !checked.id) return null;
    return document.querySelector('#pay-with-' + checked.id + '-form .pb-card-payment-form');
  }

  function setConfirmButtonsDisabled(disabled) {
    document.querySelectorAll('#payment-confirmation button, .js-payment-confirmation button').forEach(function (btn) {
      btn.disabled = !!disabled;
      if (disabled) {
        btn.classList.add('disabled');
      } else {
        btn.classList.remove('disabled');
      }
    });
  }

  function fieldInForm(form, id) {
    return form.querySelector('#' + id) || document.getElementById(id);
  }

  function nativeFormSubmit(form) {
    HTMLFormElement.prototype.submit.call(form);
  }

  var payInFlight = false;

  function processCardPayment(form) {
    if (!form) return Promise.resolve();
    if (form.dataset.pbReady === '1') {
      nativeFormSubmit(form);
      return Promise.resolve();
    }
    if (payInFlight) return Promise.resolve();
    payInFlight = true;
    showError('');
    setConfirmButtonsDisabled(true);

    return tokenizeCard().then(function (tokenResult) {
      var tokenInput = fieldInForm(form, 'pb-gateway-token');
      var companyInput = fieldInForm(form, 'pb-payment-company-code');
      var amountInput = fieldInForm(form, 'pb-amount-cents');
      var profileInput = fieldInForm(form, 'pb-payment-profile-id');
      var threeInput = fieldInForm(form, 'pb-three-ds');
      if (!tokenInput) {
        throw new Error('Campo de token ausente no formulário.');
      }
      tokenInput.value = tokenResult.gateway_token || '';
      if (!tokenInput.value) {
        throw new Error('Token ausente na resposta.');
      }
      if (companyInput) companyInput.value = tokenResult.payment_company_code;
      var amount = selectedAmountCents();
      if (amountInput) amountInput.value = String(amount);
      var holderPost = fieldInForm(form, 'pb-card-holder-post');
      var expiryPost = fieldInForm(form, 'pb-card-expiry-post');
      var last4Post = fieldInForm(form, 'pb-card-last4');
      var holderEl = fieldInForm(form, 'pb-card-holder') || document.getElementById('pb-card-holder');
      var expiryEl = fieldInForm(form, 'pb-card-expiry') || document.getElementById('pb-card-expiry');
      var numberEl = fieldInForm(form, 'pb-card-number') || document.getElementById('pb-card-number');
      if (holderPost && holderEl) holderPost.value = normalizeHolderName(holderEl.value || '');
      if (expiryPost && expiryEl) expiryPost.value = String(expiryEl.value || '').replace(/\s+/g, '');
      if (last4Post && numberEl) {
        var digits = String(numberEl.value || '').replace(/\D+/g, '');
        last4Post.value = digits.length >= 4 ? digits.slice(-4) : '';
      }
      var installments = parseInt((fieldInForm(form, 'pb-card-installments') || {}).value || '1', 10) || 1;
      return maybeRun3ds(tokenResult, amount, installments).then(function (three) {
        if (three) {
          if (profileInput) profileInput.value = String(three.payment_profile_id || '');
          if (threeInput) threeInput.value = JSON.stringify(three.three_ds || {});
        }
        form.dataset.pbReady = '1';
        nativeFormSubmit(form);
      });
    }).catch(function (err) {
      showError(err.message || String(err));
      form.dataset.pbReady = '0';
      setConfirmButtonsDisabled(false);
    }).then(function () {
      payInFlight = false;
    });
  }

  function onSubmit(ev) {
    var form = ev.target;
    if (!form || !form.classList || !form.classList.contains('pb-card-payment-form')) return;
    if (form.dataset.pbReady === '1') return;
    ev.preventDefault();
    ev.stopPropagation();
    processCardPayment(form);
  }

  /**
   * PrestaShop core does `$('#pay-with-…-form form').submit()` after "Place Order".
   * Native HTMLFormElement.submit() does not fire the submit event — patch it.
   */
  function armCardForm(form) {
    if (!form || form.dataset.pbArmed === '1') return;
    form.dataset.pbArmed = '1';
    form.submit = function patchedSubmit() {
      if (form.dataset.pbReady === '1') {
        nativeFormSubmit(form);
        return;
      }
      processCardPayment(form);
    };
  }

  function armAllCardForms() {
    document.querySelectorAll('.pb-card-payment-form').forEach(armCardForm);
  }

  function onConfirmClick(ev) {
    var btn = ev.target && ev.target.closest
      ? ev.target.closest('#payment-confirmation button, .js-payment-confirmation button')
      : null;
    if (!btn) return;
    var form = getActiveCardForm();
    if (!form) return;
    // Card option selected — take over before PrestaShop posts an empty form.
    ev.preventDefault();
    ev.stopPropagation();
    if (typeof ev.stopImmediatePropagation === 'function') {
      ev.stopImmediatePropagation();
    }
    armCardForm(form);
    processCardPayment(form);
  }

  function onCardFieldBeforeInput(ev) {
    var el = ev.target;
    if (!el || !el.id) return;
    if (el.id !== 'pb-card-number' && el.id !== 'pb-card-expiry' && el.id !== 'pb-card-cvv') {
      return;
    }
    if (typeof ev.data !== 'string' || ev.data === '') return;
    // Reject keystrokes that are only letters/symbols (paste with digits is sanitized on input).
    if (!/\d/.test(ev.data)) {
      ev.preventDefault();
    }
  }

  document.addEventListener('submit', onSubmit, true);
  document.addEventListener('click', onConfirmClick, true);
  // Delegated: payment option HTML may be injected after DOMContentLoaded.
  document.addEventListener('beforeinput', onCardFieldBeforeInput, true);
  document.addEventListener('input', onCardFieldInput, true);
  document.addEventListener('focusout', onCardFieldBlur, true);

  armAllCardForms();
  prefetchInstallments();
  if (typeof MutationObserver !== 'undefined') {
    var mo = new MutationObserver(function () {
      armAllCardForms();
      if (cfg().installmentsEnabled && document.getElementById('pb-card-installments') && !lastFeesBin) {
        prefetchInstallments();
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }

  // jQuery path used by PrestaShop core — bind if available.
  function bindJquerySubmit() {
    if (!window.jQuery) return;
    window.jQuery(document).off('submit.pbconnect').on('submit.pbconnect', '.pb-card-payment-form', function (ev) {
      var form = this;
      if (form.dataset.pbReady === '1') return;
      ev.preventDefault();
      ev.stopPropagation();
      processCardPayment(form);
    });
  }
  bindJquerySubmit();
  document.addEventListener('DOMContentLoaded', function () {
    bindJquerySubmit();
    prefetchInstallments();
  });
})();
