<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Validation;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Taler\Api\Order\Dto\Amount;

/**
 * Validates block amount: local format (2+ letter currency, max 2 decimals) + SDK DTO.
 */
final class TalerAmountInputValidator implements TalerAmountInputValidatorInterface {

  use StringTranslationTrait;

  /**
   * Pattern: CURRENCY:VALUE — currency min 2 ASCII letters; value integer or decimal with ≤2 fractional digits.
   *
   * Examples: KUDOS:0.11, EUR:11.12
   */
  private const AMOUNT_PATTERN = '/^([A-Za-z]{2,}):(\d+)(\.\d{1,2})?$/';

  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(string $amount): ?TranslatableMarkup {
    $amount = trim($amount);
    if ($amount === '') {
      return $this->t('Amount is required.');
    }

    if (!preg_match(self::AMOUNT_PATTERN, $amount)) {
      return $this->t('Amount must be in the form CURRENCY:VALUE, for example EUR:11.12 or KUDOS:0.11. Use at least two letters for the currency and at most two decimal places.');
    }

    try {
      new Amount($amount);
    }
    catch (\InvalidArgumentException $e) {
      return $this->t('Amount is not accepted by Taler: @detail', [
        '@detail' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}
