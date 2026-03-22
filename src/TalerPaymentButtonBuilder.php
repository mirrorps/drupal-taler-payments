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

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['taler-payment-button-wrapper'],
      ],
    ];

    $build['link'] = [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => Url::fromRoute('<front>'),
      '#attributes' => [
        'class' => ['taler-payment-button'],
      ],
    ];

    $build['#attached'] = [
      'library' => ['taler_payments/payment_button'],
    ];

    return $build;
  }

}
