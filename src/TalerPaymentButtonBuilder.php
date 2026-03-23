<?php

declare(strict_types=1);

namespace Drupal\taler_payments;

use Drupal\Core\Url;

/**
 * Default implementation of {@link TalerPaymentButtonBuilderInterface}.
 */
final class TalerPaymentButtonBuilder implements TalerPaymentButtonBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function build(array $configuration): array {
    $button_text = (string) ($configuration['button_text'] ?? '');
    $title = trim((string) ($configuration['label'] ?? ''));
    $amount = trim((string) ($configuration['amount'] ?? ''));
    $summary = trim((string) ($configuration['summary'] ?? ''));

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['taler-payment-button-wrapper'],
      ],
    ];

    $build['link'] = [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => Url::fromRoute('taler_payments.checkout_start', [], [
        'query' => [
          'title' => $title,
          'amount' => $amount,
          'summary' => $summary,
        ],
      ]),
      '#attributes' => [
        'class' => ['taler-payment-button'],
        'data-disable-once' => 'true',
      ],
    ];

    $build['#attached'] = [
      'library' => ['taler_payments/payment_button'],
    ];

    return $build;
  }

}
