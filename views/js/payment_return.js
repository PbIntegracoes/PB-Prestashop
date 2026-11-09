/**
 * PB Connect — PIX confirmation: copy button + status polling.
 * Same behaviour as Magento / Woo (poll until paid, then green badge).
 */
(function () {
  'use strict';

  function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
      var textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'absolute';
      textArea.style.left = '-999999px';
      document.body.prepend(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        resolve();
      } catch (e) {
        reject(e);
      } finally {
        textArea.remove();
      }
    });
  }

  function initCopy(root) {
    var input = root.querySelector('.pb-pix-code');
    var btn = root.querySelector('.pb-pix-copy-btn');
    var copied = root.querySelector('.pb-pix-copied');
    var label = root.querySelector('.pb-pix-copy-label');
    if (!input || !btn) {
      return;
    }

    var original = label ? label.textContent : '';
    var copiedLabel = root.getAttribute('data-label-copied') || 'Copied ✔';

    btn.addEventListener('click', function () {
      copyToClipboard(input.value).then(function () {
        if (label) {
          label.textContent = copiedLabel.replace(/\s*✔\s*$/, '').trim() || 'Copied';
        }
        if (copied) {
          copied.hidden = false;
        }
        setTimeout(function () {
          if (label) {
            label.textContent = original;
          }
          if (copied) {
            copied.hidden = true;
          }
        }, 2000);
      });
    });
  }

  function initPoll(root) {
    var detailsEl = root.querySelector('.pb-pix-details');
    var statusEl = root.querySelector('.pb-pix-payment-status');
    var statusTextEl = root.querySelector('.pb-pix-status-text');
    var labelWaiting = root.getAttribute('data-label-waiting') || 'Awaiting your payment';
    var labelConfirmed = root.getAttribute('data-label-confirmed') || 'Payment confirmed';

    if (statusEl && statusEl.classList.contains('is-confirmed')) {
      return;
    }

    var statusUrl = root.getAttribute('data-status-url');
    if (!statusUrl) {
      return;
    }

    var pollInterval = parseInt(root.getAttribute('data-poll-interval'), 10) || 3000;
    var pollTimeout = parseInt(root.getAttribute('data-poll-timeout'), 10) || 600000;

    function showConfirmed() {
      if (detailsEl) {
        detailsEl.style.display = 'none';
      }
      if (statusEl) {
        statusEl.classList.remove('is-waiting');
        statusEl.classList.add('is-confirmed');
      }
      if (statusTextEl) {
        statusTextEl.textContent = labelConfirmed;
      }
    }

    function isPaid(payload) {
      if (!payload || payload.success === false) {
        return false;
      }
      if (payload.paid === true || payload.paid === 1 || payload.paid === '1') {
        return true;
      }
      var status = String(payload.data || '').toLowerCase();
      return status === 'paid' || status === 'processing' || status === 'completed' || status === 'complete';
    }

    function poll() {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', statusUrl, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) {
          return;
        }
        var payload;
        try {
          payload = JSON.parse(xhr.responseText);
        } catch (e) {
          return;
        }
        if (isPaid(payload)) {
          clearInterval(intervalId);
          showConfirmed();
        }
      };
      xhr.send();
    }

    var intervalId = setInterval(poll, pollInterval);
    setTimeout(function () {
      clearInterval(intervalId);
    }, pollTimeout);
    setTimeout(poll, 1200);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('pbconnect-pix-success');
    if (!root) {
      return;
    }
    initCopy(root);
    initPoll(root);
  });
})();
