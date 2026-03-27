<?php

declare(strict_types=1);

namespace Drupal\taler_payments\PublicText;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Resolves public checkout text from configuration with safe fallbacks.
 */
final class TalerPublicTextProvider implements TalerPublicTextProviderInterface {

  private const DEFAULT_CALL_TO_ACTION = 'Review your order details and complete payment with GNU Taler.';
  private const DEFAULT_THANK_YOU_MESSAGE = 'Payment received. Thank you!';
  private const DEFAULT_PAYMENT_BUTTON_CTA = 'Pay with Taler wallet in the browser';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDefaultCallToAction(): string {
    return self::DEFAULT_CALL_TO_ACTION;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultThankYouMessage(): string {
    return self::DEFAULT_THANK_YOU_MESSAGE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPaymentButtonCta(): string {
    return self::DEFAULT_PAYMENT_BUTTON_CTA;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallToAction(): string {
    return $this->resolveConfiguredText('public_call_to_action', self::DEFAULT_CALL_TO_ACTION);
  }

  /**
   * {@inheritdoc}
   */
  public function getThankYouMessage(): string {
    return $this->resolveConfiguredText('public_thank_you_message', self::DEFAULT_THANK_YOU_MESSAGE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentButtonCta(): string {
    return $this->resolveConfiguredText('public_payment_button_cta', self::DEFAULT_PAYMENT_BUTTON_CTA);
  }

  /**
   * {@inheritdoc}
   */
  public function getFulfillmentMessageForApi(): string {
    $message = $this->getThankYouMessage();
    $sanitized = preg_replace('/[^\p{L}\p{N}\s.,!?\-:;"()\/\']+/u', '', $message);
    $sanitized = trim(preg_replace('/\s+/', ' ', (string) $sanitized) ?? '');

    return $sanitized !== '' ? $sanitized : self::DEFAULT_THANK_YOU_MESSAGE;
  }

  /**
   * Returns configured text or default when empty.
   */
  private function resolveConfiguredText(string $key, string $default): string {
    $configured = $this->configFactory->get('taler_payments.settings')->get($key);
    $value = trim((string) $configured);
    return $value !== '' ? $value : $default;
  }

}
