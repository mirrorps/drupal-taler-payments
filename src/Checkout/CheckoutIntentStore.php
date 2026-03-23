<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Checkout;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-backed storage for checkout intents.
 */
final class CheckoutIntentStore implements CheckoutIntentStoreInterface {

  private const SESSION_KEY = 'taler_payments.checkout_intents';

  public function __construct(
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getReusableIntent(string $fingerprint, int $max_age_seconds): ?array {
    $session = $this->requestStack->getCurrentRequest()?->getSession();
    if ($session === NULL) {
      return NULL;
    }

    /** @var array<string, array<string, mixed>> $intents */
    $intents = $session->get(self::SESSION_KEY, []);
    $intent = $intents[$fingerprint] ?? NULL;
    if (!is_array($intent)) {
      return NULL;
    }

    $created_at = (int) ($intent['created_at'] ?? 0);
    if ($created_at <= 0 || (time() - $created_at) > $max_age_seconds) {
      return NULL;
    }

    return $intent;
  }

  /**
   * {@inheritdoc}
   */
  public function saveIntent(string $fingerprint, array $intent): void {
    $session = $this->requestStack->getCurrentRequest()?->getSession();
    if ($session === NULL) {
      return;
    }

    /** @var array<string, array<string, mixed>> $intents */
    $intents = $session->get(self::SESSION_KEY, []);
    $intents[$fingerprint] = $intent;
    $session->set(self::SESSION_KEY, $intents);
  }

  /**
   * {@inheritdoc}
   */
  public function getIntentByOrderId(string $order_id): ?array {
    $session = $this->requestStack->getCurrentRequest()?->getSession();
    if ($session === NULL) {
      return NULL;
    }

    /** @var array<string, array<string, mixed>> $intents */
    $intents = $session->get(self::SESSION_KEY, []);
    foreach ($intents as $intent) {
      if (($intent['order_id'] ?? '') === $order_id) {
        return $intent;
      }
    }

    return NULL;
  }

}
