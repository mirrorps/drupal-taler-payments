<?php

declare(strict_types=1);

namespace Drupal\taler_payments;

/**
 * Builds a render array for the Taler payment button block.
 */
interface TalerPaymentButtonBuilderInterface {

  /**
   * Builds a render array for the Taler payment button.
   *
   * Block configuration: button_text (string), summary (string), amount (string).
   * @param array $configuration
   *
   * @return array
   */
  public function build(array $configuration): array;

}
