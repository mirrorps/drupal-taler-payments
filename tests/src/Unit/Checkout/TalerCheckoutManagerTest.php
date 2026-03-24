<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Checkout;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\taler_payments\Checkout\CheckoutIntentStoreInterface;
use Drupal\taler_payments\Checkout\TalerCheckoutManager;
use Drupal\taler_payments\Service\TalerClientManager;
use PHPUnit\Framework\TestCase;
use Taler\Api\Dto\Timestamp;
use Taler\Api\Order\Dto\CheckPaymentPaidResponse;
use Taler\Api\Order\Dto\CheckPaymentUnpaidResponse;
use Taler\Api\Order\Dto\GetOrderRequest;
use Taler\Api\Order\Dto\PostOrderRequest;
use Taler\Api\Order\Dto\PostOrderResponse;
use Taler\Api\Order\OrderClient;
use Taler\Exception\TalerException;
use Taler\Taler;

/**
 * @coversDefaultClass \Drupal\taler_payments\Checkout\TalerCheckoutManager
 * @group taler_payments
 */
final class TalerCheckoutManagerTest extends TestCase {

  private function createUnpaidResponse(string $pay_uri = 'taler://pay/mock'): CheckPaymentUnpaidResponse {
    return new CheckPaymentUnpaidResponse(
      order_status: 'unpaid',
      taler_pay_uri: $pay_uri,
      creation_time: new Timestamp(1700000000),
      summary: 'Demo order',
      total_amount: NULL,
      already_paid_order_id: NULL,
      already_paid_fulfillment_url: NULL,
      order_status_url: 'https://merchant.example/order-status',
    );
  }

  /**
   * @covers ::beginCheckout
   */
  public function testBeginCheckoutReusesExistingUnpaidIntent(): void {
    $existing_intent = [
      'title' => 'Existing',
      'order_id' => 'order-existing',
      'token' => 'tok-existing',
      'amount' => 'EUR:1.00',
      'summary' => 'Summary',
      'created_at' => time() - 5,
    ];

    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->expects($this->once())
      ->method('getReusableIntent')
      ->willReturn($existing_intent);
    $store->expects($this->never())->method('saveIntent');

    $order_api = $this->createMock(OrderClient::class);
    $order_api->expects($this->once())
      ->method('getOrder')
      ->with(
        'order-existing',
        $this->callback(static fn (mixed $request): bool => $request instanceof GetOrderRequest && $request->token === 'tok-existing'),
      )
      ->willReturn($this->createUnpaidResponse('taler://pay/reused'));

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->expects($this->never())->method('generate');

    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->beginCheckout('Existing', 'EUR:1.00', 'Summary');

    $this->assertSame('order-existing', $result['order_id']);
    $this->assertSame('unpaid', $result['status']);
    $this->assertSame('taler://pay/reused', $result['taler_pay_uri']);
  }

  /**
   * @covers ::beginCheckout
   */
  public function testBeginCheckoutCreatesAndSavesNewIntentWhenNoReusableOneExists(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->expects($this->once())
      ->method('getReusableIntent')
      ->willReturn(NULL);
    $store->expects($this->once())
      ->method('saveIntent')
      ->with(
        $this->isType('string'),
        $this->callback(static function (array $intent): bool {
          return ($intent['title'] ?? '') === 'Checkout'
            && ($intent['order_id'] ?? '') === 'order-created'
            && ($intent['amount'] ?? '') === 'EUR:2.50'
            && ($intent['status'] ?? '') === 'unpaid';
        }),
      );

    $order_api = $this->createMock(OrderClient::class);
    $order_api->expects($this->once())
      ->method('createOrder')
      ->with($this->isInstanceOf(PostOrderRequest::class))
      ->willReturn(new PostOrderResponse('order-created', 'tok-created'));
    $order_api->expects($this->once())
      ->method('getOrder')
      ->with(
        'order-created',
        $this->callback(static fn (mixed $request): bool => $request instanceof GetOrderRequest && $request->token === 'tok-created'),
      )
      ->willReturn($this->createUnpaidResponse('taler://pay/new'));

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('uuid-generated');

    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->beginCheckout('   ', 'EUR:2.50', 'Order summary');

    $this->assertSame('order-created', $result['order_id']);
    $this->assertSame('Checkout', $result['title']);
    $this->assertSame('unpaid', $result['status']);
    $this->assertSame('taler://pay/new', $result['taler_pay_uri']);
  }

  /**
   * @covers ::beginCheckout
   */
  public function testBeginCheckoutCreatesNewIntentWhenReusableIntentIsNotUnpaid(): void {
    $existing_intent = [
      'title' => 'Existing',
      'order_id' => 'order-existing',
      'token' => 'tok-existing',
      'amount' => 'EUR:1.00',
      'summary' => 'Summary',
      'created_at' => time() - 5,
    ];

    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->expects($this->once())
      ->method('getReusableIntent')
      ->willReturn($existing_intent);
    $store->expects($this->once())
      ->method('saveIntent')
      ->with(
        $this->isType('string'),
        $this->callback(static fn (array $intent): bool => ($intent['order_id'] ?? '') === 'order-created'),
      );

    $paid_response = $this->createMock(CheckPaymentPaidResponse::class);
    $order_api = $this->createMock(OrderClient::class);
    $order_api->expects($this->once())
      ->method('createOrder')
      ->willReturn(new PostOrderResponse('order-created', 'tok-created'));
    $order_api->expects($this->exactly(2))
      ->method('getOrder')
      ->withAnyParameters()
      ->willReturnOnConsecutiveCalls(
        $paid_response,
        $this->createUnpaidResponse('taler://pay/new'),
      );

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('uuid-generated');

    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->beginCheckout('Existing', 'EUR:1.00', 'Summary');

    $this->assertSame('order-created', $result['order_id']);
    $this->assertSame('unpaid', $result['status']);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdReturnsNullWhenIntentMissing(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('missing')->willReturn(NULL);

    $client_manager = $this->createMock(TalerClientManager::class);
    $uuid = $this->createMock(UuidInterface::class);

    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $this->assertNull($manager->getCheckoutByOrderId('missing'));
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMapsPaidResponse(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-1')->willReturn([
      'order_id' => 'order-1',
      'token' => 'tok-1',
      'title' => 'Order 1',
    ]);

    $paid_response = $this->createMock(CheckPaymentPaidResponse::class);
    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willReturn($paid_response);

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-1');

    $this->assertSame('paid', $result['status'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMapsAlreadyPaidUnpaidResponseAsPaid(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-1')->willReturn([
      'order_id' => 'order-1',
      'token' => 'tok-1',
    ]);

    $response = new CheckPaymentUnpaidResponse(
      order_status: 'unpaid',
      taler_pay_uri: 'taler://pay/mock',
      creation_time: new Timestamp(1700000000),
      summary: 'Demo order',
      total_amount: NULL,
      already_paid_order_id: 'paid-123',
      already_paid_fulfillment_url: 'https://merchant.example/fulfillment',
      order_status_url: 'https://merchant.example/order-status',
    );
    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willReturn($response);

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-1');

    $this->assertSame('paid', $result['status'] ?? NULL);
    $this->assertSame('paid-123', $result['already_paid_order_id'] ?? NULL);
    $this->assertSame('https://merchant.example/fulfillment', $result['already_paid_fulfillment_url'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMaps404AsNotFound(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-404')->willReturn([
      'order_id' => 'order-404',
      'token' => '',
    ]);

    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willThrowException(new TalerException('not found', 404));

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-404');

    $this->assertSame('not_found', $result['status'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMapsNon404TalerExceptionAsUnknown(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-500')->willReturn([
      'order_id' => 'order-500',
      'token' => '',
    ]);

    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willThrowException(new TalerException('error', 500));

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-500');

    $this->assertSame('unknown', $result['status'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMapsUnexpectedThrowableAsUnknown(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-throwable')->willReturn([
      'order_id' => 'order-throwable',
      'token' => '',
    ]);

    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willThrowException(new \RuntimeException('boom'));

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-throwable');

    $this->assertSame('unknown', $result['status'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

  /**
   * @covers ::getCheckoutByOrderId
   */
  public function testGetCheckoutByOrderIdMapsUnknownResponseTypeAsUnknown(): void {
    $store = $this->createMock(CheckoutIntentStoreInterface::class);
    $store->method('getIntentByOrderId')->with('order-weird')->willReturn([
      'order_id' => 'order-weird',
      'token' => '',
    ]);

    $order_api = $this->createMock(OrderClient::class);
    $order_api->method('getOrder')->willReturn([]);

    $client = $this->createMock(Taler::class);
    $client->method('order')->willReturn($order_api);

    $client_manager = $this->createMock(TalerClientManager::class);
    $client_manager->method('getClient')->willReturn($client);

    $uuid = $this->createMock(UuidInterface::class);
    $manager = new TalerCheckoutManager($client_manager, $store, $uuid);
    $result = $manager->getCheckoutByOrderId('order-weird');

    $this->assertSame('unknown', $result['status'] ?? NULL);
    $this->assertSame('', $result['taler_pay_uri'] ?? NULL);
  }

}
