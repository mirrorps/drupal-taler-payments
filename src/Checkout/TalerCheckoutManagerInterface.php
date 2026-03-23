<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Checkout;

/**
 * Orchestrates checkout intents and Taler order lifecycle.
 */
interface TalerCheckoutManagerInterface {

  /**
   * Reuses a recent unpaid order or creates a new one.
   *
   * Checkout intent data including order_id/summary/amount/token.
   * @return array<string, mixed>
   */
  public function beginCheckout(string $title, string $amount, string $summary): array;

  /**
   * Returns checkout details by order id for current visitor session.
   *
   * Checkout intent/status payload or NULL when not available.
   * @return array<string, mixed>|null
   */
  public function getCheckoutByOrderId(string $order_id): ?array;

}
