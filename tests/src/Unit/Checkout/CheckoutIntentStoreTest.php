<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Checkout;

use Drupal\taler_payments\Checkout\CheckoutIntentStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @coversDefaultClass \Drupal\taler_payments\Checkout\CheckoutIntentStore
 * @group taler_payments
 */
final class CheckoutIntentStoreTest extends TestCase {

  /**
   * @covers ::getReusableIntent
   */
  public function testGetReusableIntentReturnsNullWithoutCurrentRequest(): void {
    $store = new CheckoutIntentStore(new RequestStack());
    $this->assertNull($store->getReusableIntent('abc', 60));
  }

  /**
   * @covers ::getReusableIntent
   */
  public function testGetReusableIntentReturnsFreshIntent(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'fp-1' => [
          'order_id' => 'order-1',
          'created_at' => time() - 10,
        ],
      ]);

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $intent = $store->getReusableIntent('fp-1', 120);

    $this->assertNotNull($intent);
    $this->assertSame('order-1', $intent['order_id']);
  }

  /**
   * @covers ::getReusableIntent
   */
  public function testGetReusableIntentRejectsStaleIntent(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'fp-1' => [
          'order_id' => 'order-1',
          'created_at' => time() - 999,
        ],
      ]);

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $this->assertNull($store->getReusableIntent('fp-1', 120));
  }

  /**
   * @covers ::getReusableIntent
   */
  public function testGetReusableIntentRejectsNonArrayIntentPayload(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'fp-1' => 'not-an-array',
      ]);

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $this->assertNull($store->getReusableIntent('fp-1', 120));
  }

  /**
   * @covers ::getReusableIntent
   */
  public function testGetReusableIntentRejectsMissingCreatedAt(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'fp-1' => [
          'order_id' => 'order-1',
        ],
      ]);

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $this->assertNull($store->getReusableIntent('fp-1', 120));
  }

  /**
   * @covers ::saveIntent
   */
  public function testSaveIntentStoresPayloadByFingerprint(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'old' => ['order_id' => 'old-order'],
      ]);

    $session->expects($this->once())
      ->method('set')
      ->with(
        'taler_payments.checkout_intents',
        $this->callback(static function (array $value): bool {
          return isset($value['old'], $value['new'])
            && $value['new']['order_id'] === 'new-order';
        }),
      );

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $store->saveIntent('new', ['order_id' => 'new-order']);
  }

  /**
   * @covers ::saveIntent
   */
  public function testSaveIntentWithoutCurrentRequestDoesNothing(): void {
    $store = new CheckoutIntentStore(new RequestStack());
    $store->saveIntent('new', ['order_id' => 'new-order']);
    $this->assertTrue(TRUE);
  }

  /**
   * @covers ::getIntentByOrderId
   */
  public function testGetIntentByOrderIdFindsMatchingIntent(): void {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')
      ->with('taler_payments.checkout_intents', [])
      ->willReturn([
        'fp-1' => ['order_id' => 'order-1'],
        'fp-2' => ['order_id' => 'order-2'],
      ]);

    $request = new Request();
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);

    $store = new CheckoutIntentStore($stack);
    $intent = $store->getIntentByOrderId('order-2');

    $this->assertSame('order-2', $intent['order_id'] ?? NULL);
    $this->assertNull($store->getIntentByOrderId('missing'));
  }

  /**
   * @covers ::getIntentByOrderId
   */
  public function testGetIntentByOrderIdReturnsNullWithoutCurrentRequest(): void {
    $store = new CheckoutIntentStore(new RequestStack());
    $this->assertNull($store->getIntentByOrderId('order-1'));
  }

}
