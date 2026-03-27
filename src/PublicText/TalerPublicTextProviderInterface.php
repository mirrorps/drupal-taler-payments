<?php

declare(strict_types=1);

namespace Drupal\taler_payments\PublicText;

/**
 * Provides public-facing customizable text for checkout screens.
 */
interface TalerPublicTextProviderInterface {

  /**
   * Returns default call-to-action text.
   */
  public function getDefaultCallToAction(): string;

  /**
   * Returns default thank-you message text.
   */
  public function getDefaultThankYouMessage(): string;

  /**
   * Returns default payment button CTA text.
   */
  public function getDefaultPaymentButtonCta(): string;

  /**
   * Returns checkout call-to-action text.
   */
  public function getCallToAction(): string;

  /**
   * Returns browser success message text.
   */
  public function getThankYouMessage(): string;

  /**
   * Returns checkout button CTA text.
   */
  public function getPaymentButtonCta(): string;

  /**
   * Returns API-safe fulfillment message text.
   */
  public function getFulfillmentMessageForApi(): string;

}
