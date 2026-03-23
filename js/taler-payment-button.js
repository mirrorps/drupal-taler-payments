/**
 * @file
 * Front-end guard against accidental multi-click order creation.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.talerPaymentButton = {
    attach(context) {
      once('taler-payment-button', '.taler-payment-button[data-disable-once="true"]', context).forEach((element) => {
        element.addEventListener('click', (event) => {
          if (element.classList.contains('is-disabled')) {
            event.preventDefault();
            return;
          }

          element.classList.add('is-disabled');
          element.setAttribute('aria-disabled', 'true');
          element.setAttribute('tabindex', '-1');
        });
      });

    },
  };
})(Drupal, once);
