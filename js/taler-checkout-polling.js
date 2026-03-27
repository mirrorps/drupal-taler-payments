/**
 * @file
 * Polls checkout order status and updates UI.
 */

(function (Drupal, once) {
  'use strict';

  const DEFAULT_INTERVAL_MS = 5000;
  const MAX_UNKNOWN_POLLS = 20;

  function isFinalStatus(status) {
    return status === 'paid' || status === 'cancelled' || status === 'not_found';
  }

  function statusToPresentation(status, successMessage) {
    switch (status) {
      case 'paid':
        return { text: successMessage || Drupal.t('Payment completed.'), className: 'taler-checkout-status-success', hidePayLink: true };
      case 'cancelled':
        return { text: Drupal.t('Payment cancelled.'), className: 'taler-checkout-status-warning', hidePayLink: true };
      case 'not_found':
        return { text: Drupal.t('This checkout order is no longer available.'), className: 'taler-checkout-status-warning', hidePayLink: true };
      case 'unpaid':
        return { text: Drupal.t('Payment pending. Continue in your Taler wallet.'), className: 'taler-checkout-status-pending', hidePayLink: false };
      default:
        return { text: Drupal.t('Checking payment status…'), className: 'taler-checkout-status-pending', hidePayLink: false };
    }
  }

  function updateStatusElement(statusEl, status, successMessage) {
    const pres = statusToPresentation(status, successMessage);
    statusEl.textContent = pres.text;
    statusEl.classList.remove('taler-checkout-status-success', 'taler-checkout-status-pending', 'taler-checkout-status-warning');
    statusEl.classList.add(pres.className);
    statusEl.setAttribute('data-taler-status', status);
    return pres;
  }

  function hidePayLink(payLinkEl) {
    if (!payLinkEl) {
      return;
    }
    payLinkEl.classList.add('is-hidden');
    payLinkEl.setAttribute('aria-hidden', 'true');
    payLinkEl.setAttribute('tabindex', '-1');
  }

  function hideWalletHint(walletHintEl) {
    if (!walletHintEl) {
      return;
    }
    walletHintEl.classList.add('is-hidden');
    walletHintEl.setAttribute('aria-hidden', 'true');
  }

  function hideQrCode(qrIntroEl, qrCodeEl) {
    if (qrIntroEl) {
      qrIntroEl.classList.add('is-hidden');
      qrIntroEl.setAttribute('aria-hidden', 'true');
    }
    if (qrCodeEl) {
      qrCodeEl.classList.add('is-hidden');
      qrCodeEl.setAttribute('aria-hidden', 'true');
    }
  }

  function initQrCode(qrCodeEl) {
    if (!qrCodeEl || typeof window.QRCode !== 'function') {
      return;
    }

    const payUri = qrCodeEl.getAttribute('data-taler-pay-uri');
    if (!payUri || !payUri.startsWith('taler://')) {
      return;
    }

    if (qrCodeEl.getAttribute('data-qr-initialized') === 'true') {
      return;
    }

    qrCodeEl.setAttribute('data-qr-initialized', 'true');
    // 192x192 keeps a comfortable scan target on mobile screens.
    new window.QRCode(qrCodeEl, {
      text: payUri,
      width: 192,
      height: 192,
      colorDark: '#111827',
      colorLight: '#ffffff',
      correctLevel: window.QRCode.CorrectLevel.M,
    });
  }

  Drupal.behaviors.talerCheckoutPolling = {
    attach(context, settings) {
      once('taler-checkout-polling', '.taler-checkout-page', context).forEach((pageEl) => {
        const orderId = settings?.talerPaymentsCheckout?.orderId;
        const statusUrl = settings?.talerPaymentsCheckout?.statusUrl;
        const intervalMs = Number(settings?.talerPaymentsCheckout?.pollIntervalMs) || DEFAULT_INTERVAL_MS;
        const successMessage = settings?.talerPaymentsCheckout?.successMessage;

        const statusEl = pageEl.querySelector('.taler-checkout-status');
        const payLinkEl = pageEl.querySelector('.taler-checkout-pay-link');
        const walletHintEl = pageEl.querySelector('.taler-checkout-wallet-hint');
        const qrIntroEl = pageEl.querySelector('.taler-checkout-qr-intro');
        const qrCodeEl = pageEl.querySelector('.taler-checkout-qr-code');

        if (!orderId || !statusUrl || !statusEl) {
          return;
        }

        initQrCode(qrCodeEl);

        // Start from server-rendered state when available.
        let currentStatus = statusEl.getAttribute('data-taler-status') || 'unknown';
        updateStatusElement(statusEl, currentStatus, successMessage);

        if (isFinalStatus(currentStatus)) {
          const pres = statusToPresentation(currentStatus, successMessage);
          if (pres.hidePayLink) {
            hidePayLink(payLinkEl);
            hideWalletHint(walletHintEl);
            hideQrCode(qrIntroEl, qrCodeEl);
          }
          return;
        }

        let unknownPolls = 0;
        let stopped = false;
        let timerId = null;

        const stop = () => {
          stopped = true;
          if (timerId) {
            window.clearInterval(timerId);
            timerId = null;
          }
        };

        const poll = async () => {
          if (stopped) {
            return;
          }

          try {
            const res = await window.fetch(statusUrl, {
              method: 'GET',
              credentials: 'same-origin',
              headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            const currentStatus = (data && typeof data.status === 'string') ? data.status : 'unknown';

            const pres = updateStatusElement(statusEl, currentStatus, successMessage);

            if (pres.hidePayLink) {
              hidePayLink(payLinkEl);
              hideWalletHint(walletHintEl);
              hideQrCode(qrIntroEl, qrCodeEl);
            }

            if (isFinalStatus(currentStatus)) {
              stop();
              return;
            }

            if (currentStatus === 'unknown') {
              unknownPolls += 1;
              if (unknownPolls >= MAX_UNKNOWN_POLLS) {
                stop();
              }
            } else {
              unknownPolls = 0;
            }
          } catch (e) {
            unknownPolls += 1;
            if (unknownPolls >= MAX_UNKNOWN_POLLS) {
              stop();
            }
          }
        };

        // Poll immediately, then on interval.
        poll();
        timerId = window.setInterval(poll, intervalMs);
      });
    },
  };
})(Drupal, once);

