<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Checkout;

/**
 * Stores checkout intents per visitor session.
 */
interface CheckoutIntentStoreInterface {

  /**
   * Returns reusable checkout intent for a payment fingerprint.
   * 
   * Stored intent data or NULL when unavailable/stale.
   * @return array<string, mixed>|null  
   */
  public function getReusableIntent(string $fingerprint, int $max_age_seconds): ?array;

  /**
   * Stores checkout intent payload.
   *
   * Intent payload.
   * @param array<string, mixed> $intent
   */
  public function saveIntent(string $fingerprint, array $intent): void;

  /**
   * Returns intent by order id.
   *
   * Intent payload or NULL.
   * @return array<string, mixed>|null
   */
  public function getIntentByOrderId(string $order_id): ?array;

}
