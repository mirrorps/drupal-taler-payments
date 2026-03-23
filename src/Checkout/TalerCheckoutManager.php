<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Checkout;

use Drupal\Component\Uuid\UuidInterface;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taler_payments\Service\TalerClientManager;
use Taler\Api\Order\Dto\Amount;
use Taler\Api\Order\Dto\CheckPaymentPaidResponse;
use Taler\Api\Order\Dto\CheckPaymentUnpaidResponse;
use Taler\Api\Order\Dto\GetOrderRequest;
use Taler\Api\Order\Dto\OrderV0;
use Taler\Api\Order\Dto\PostOrderRequest;
use Taler\Api\Order\Dto\PostOrderResponse;
use Taler\Exception\TalerException;

/**
 * Creates/reuses checkout intents backed by Taler SDK order status.
 */
final class TalerCheckoutManager implements TalerCheckoutManagerInterface {

  private const INTENT_REUSE_SECONDS = 120;
  // private const LOGGER_CHANNEL = 'taler_payments';

  public function __construct(
    private readonly TalerClientManager $talerClientManager,
    private readonly CheckoutIntentStoreInterface $intentStore,
    private readonly UuidInterface $uuid,
    // private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function beginCheckout(string $title, string $amount, string $summary): array {
    $title = trim($title);
    $amount = trim($amount);
    $summary = trim($summary);
    $fingerprint = hash('sha256', $title . '|' . $amount . '|' . $summary);

    $existing_intent = $this->intentStore->getReusableIntent($fingerprint, self::INTENT_REUSE_SECONDS);
    if ($existing_intent !== NULL) {
      $status = $this->fetchOrderStatus(
        (string) $existing_intent['order_id'],
        (string) ($existing_intent['token'] ?? ''),
      );

      if (($status['status'] ?? '') === 'unpaid') {
        return array_merge($existing_intent, $status);
      }
    }

    $order_id = $this->uuid->generate();
    $order = new OrderV0(
      summary: $summary !== '' ? $summary : 'Taler payment',
      amount: new Amount($amount),
      order_id: $order_id,
      fulfillment_message: 'Payment received. Thank you!',
    );

    $response = $this->talerClientManager
      ->getClient()
      ->order()
      ->createOrder(new PostOrderRequest($order));

    if (!$response instanceof PostOrderResponse) {
      throw new \RuntimeException('Unexpected Taler order creation response.');
    }

    $intent = [
      'title' => $title !== '' ? $title : 'Checkout',
      'order_id' => $response->order_id,
      'token' => $response->token ?? '',
      'amount' => $amount,
      'summary' => $summary,
      'created_at' => time(),
    ];

    $status = $this->fetchOrderStatus($response->order_id, (string) ($response->token ?? ''));
    $intent = array_merge($intent, $status);
    $this->intentStore->saveIntent($fingerprint, $intent);

    return $intent;
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutByOrderId(string $order_id): ?array {
    $intent = $this->intentStore->getIntentByOrderId($order_id);
    if ($intent === NULL) {
      return NULL;
    }

    $status = $this->fetchOrderStatus(
      (string) $intent['order_id'],
      (string) ($intent['token'] ?? ''),
    );

    return array_merge($intent, $status);
  }

  /**
   * Fetches order status using SDK and maps to renderable state.
   *
   * @return array<string, mixed>
   *   Status payload.
   */
  private function fetchOrderStatus(string $order_id, string $token): array {
    // $this->loggerFactory->get(self::LOGGER_CHANNEL)->notice('Checking Taler order status for order_id={order_id}, has_token={has_token}.', [
    //   'order_id' => $order_id,
    //   'has_token' => $token !== '' ? 'yes' : 'no',
    // ]);

    try {
      $request = $token !== '' ? new GetOrderRequest(token: $token) : NULL;
      $response = $this->talerClientManager
        ->getClient()
        ->order()
        ->getOrder($order_id, $request);
    }
    catch (TalerException $exception) {
      // $this->loggerFactory->get(self::LOGGER_CHANNEL)->warning('Taler getOrder threw TalerException for order_id={order_id}, code={code}, message={message}.', [
      //   'order_id' => $order_id,
      //   'code' => (string) $exception->getCode(),
      //   'message' => $exception->getMessage(),
      // ]);

      if ($exception->getCode() === 404) {
        return [
          'status' => 'not_found',
          'taler_pay_uri' => '',
        ];
      }

      return [
        'status' => 'unknown',
        'taler_pay_uri' => '',
      ];
    }
    catch (\Throwable $exception) {
      // $this->loggerFactory->get(self::LOGGER_CHANNEL)->error('Taler getOrder threw unexpected exception for order_id={order_id}: {message}', [
      //   'order_id' => $order_id,
      //   'message' => $exception->getMessage(),
      // ]);
      return [
        'status' => 'unknown',
        'taler_pay_uri' => '',
      ];
    }

    if ($response instanceof CheckPaymentUnpaidResponse) {
      // $this->loggerFactory->get(self::LOGGER_CHANNEL)->notice('Taler getOrder unpaid for order_id={order_id}, already_paid_order_id={already_paid_order_id}.', [
      //   'order_id' => $order_id,
      //   'already_paid_order_id' => (string) ($response->already_paid_order_id ?? ''),
      // ]);

      if ($response->already_paid_order_id !== null && $response->already_paid_order_id !== '') {
        return [
          'status' => 'paid',
          'taler_pay_uri' => '',
          'already_paid_order_id' => $response->already_paid_order_id,
          'already_paid_fulfillment_url' => $response->already_paid_fulfillment_url ?? '',
        ];
      }

      return [
        'status' => 'unpaid',
        'taler_pay_uri' => $response->taler_pay_uri,
      ];
    }

    if ($response instanceof CheckPaymentPaidResponse) {
      // $this->loggerFactory->get(self::LOGGER_CHANNEL)->notice('Taler getOrder paid for order_id={order_id}.', [
      //   'order_id' => $order_id,
      // ]);
      return [
        'status' => 'paid',
        'taler_pay_uri' => '',
      ];
    }

    // $this->loggerFactory->get(self::LOGGER_CHANNEL)->warning('Taler getOrder returned unrecognized response type for order_id={order_id}, type={type}.', [
    //   'order_id' => $order_id,
    //   'type' => get_debug_type($response),
    // ]);

    return [
      'status' => 'unknown',
      'taler_pay_uri' => '',
    ];
  }

}
