<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Validation;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Validates Taler payment amount strings for block configuration.
 */
interface TalerAmountInputValidatorInterface {

  /**
   * Validates amount format and Taler {@see \Taler\Api\Order\Dto\Amount} rules.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   */
  public function validate(string $amount): ?TranslatableMarkup;

}
