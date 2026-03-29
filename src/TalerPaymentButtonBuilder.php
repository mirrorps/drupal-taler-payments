<?php

declare(strict_types=1);

namespace Drupal\taler_payments;

use Drupal\Core\Form\FormBuilderInterface;

/**
 * Default implementation of {@link TalerPaymentButtonBuilderInterface}.
 */
final class TalerPaymentButtonBuilder implements TalerPaymentButtonBuilderInterface {

  public function __construct(
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(array $configuration): array {
    $button_text = (string) ($configuration['button_text'] ?? '');
    $title = trim((string) ($configuration['label'] ?? ''));
    $amount = trim((string) ($configuration['amount'] ?? ''));
    $summary = trim((string) ($configuration['summary'] ?? ''));

    return $this->formBuilder->getForm(
      '\Drupal\taler_payments\Form\TalerCheckoutStartForm',
      [
        'button_text' => $button_text,
        'title' => $title,
        'amount' => $amount,
        'summary' => $summary,
      ],
    );
  }

}
