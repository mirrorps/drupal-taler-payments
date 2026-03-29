<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Checkout\TalerCheckoutManagerInterface;
use Drupal\taler_payments\Controller\TalerCheckoutController;
use Drupal\taler_payments\PublicText\TalerPublicTextProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @coversDefaultClass \Drupal\taler_payments\Controller\TalerCheckoutController
 * @group taler_payments
 */
final class TalerCheckoutControllerTest extends TestCase {

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  private function createTranslationStub(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn (string $string, array $args = [], array $options = []): TranslatableMarkup => new TranslatableMarkup($string, $args, $options, $translation));
    $translation->method('translateString')
      ->willReturnCallback(static fn (TranslatableMarkup $translated_string): string => $translated_string->getUntranslatedString());
    $translation->method('formatPlural')
      ->willReturnCallback(static fn (int $count, string $singular, string $plural): TranslatableMarkup => new TranslatableMarkup($count === 1 ? $singular : $plural, [], [], $translation));

    return $translation;
  }

  private function createController(
    ?TalerCheckoutManagerInterface $manager = NULL,
    ?TalerPublicTextProviderInterface $public_text_provider = NULL,
  ): TalerCheckoutController {
    $manager ??= $this->createMock(TalerCheckoutManagerInterface::class);
    $public_text_provider ??= $this->createConfiguredMock(TalerPublicTextProviderInterface::class, [
      'getCallToAction' => 'Configured CTA',
      'getThankYouMessage' => 'Configured Thank you!',
      'getPaymentButtonCta' => 'Configured Pay button',
      'getFulfillmentMessageForApi' => 'Configured Thank you!',
    ]);
    $this->initializeUrlGeneratorContainer();
    $controller = new TalerCheckoutController($manager, $public_text_provider);
    $controller->setStringTranslation($this->createTranslationStub());
    return $controller;
  }

  /**
   * Initializes a minimal container for Url::fromRoute() usage in unit tests.
   */
  private function initializeUrlGeneratorContainer(): void {
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')
      ->willReturnCallback(static function (string $route_name, array $parameters = []): string {
        $order_id = isset($parameters['order_id']) ? (string) $parameters['order_id'] : 'test-order';
        return '/mock/' . $route_name . '/' . $order_id;
      });

    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::create
   */
  public function testCreateBuildsControllerFromContainer(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $public_text_provider = $this->createMock(TalerPublicTextProviderInterface::class);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['taler_payments.checkout_manager', $manager],
        ['taler_payments.public_text_provider', $public_text_provider],
      ]);

    $controller = TalerCheckoutController::create($container);
    $this->assertInstanceOf(TalerCheckoutController::class, $controller);
  }

  /**
   * @covers ::create
   */
  public function testCreateThrowsForInvalidServiceType(): void {
    $public_text_provider = $this->createMock(TalerPublicTextProviderInterface::class);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['taler_payments.checkout_manager', new \stdClass()],
        ['taler_payments.public_text_provider', $public_text_provider],
      ]);

    $this->expectException(\InvalidArgumentException::class);
    TalerCheckoutController::create($container);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutRejectsMissingCheckoutData(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')->with('missing')->willReturn(NULL);

    $controller = $this->createController($manager);
    $this->expectException(AccessDeniedHttpException::class);
    $controller->checkout('missing');
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsPendingStateAndWalletLinkForUnpaidTalerUri(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-1')
      ->willReturn([
        'status' => 'unpaid',
        'summary' => 'Example summary',
        'amount' => 'EUR:1.00',
        'taler_pay_uri' => 'taler://pay/mock',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-1');

    $this->assertSame('container', $build['#type']);
    $this->assertSame('Configured CTA', (string) $build['intro']['#value']);
    $this->assertSame('Example summary', $build['meta']['summary']['value']['#value']);
    $this->assertSame('EUR:1.00', $build['meta']['amount']['value']['#value']);
    $this->assertSame('taler://pay/mock', $build['pay_link']['#attributes']['href']);
    $this->assertSame('Configured Pay button', (string) $build['pay_link']['#value']);
    $this->assertArrayHasKey('qr_intro', $build);
    $this->assertSame('Or scan this QR code with your mobile wallet:', (string) $build['qr_intro']['#value']);
    $this->assertArrayHasKey('qr_code', $build);
    $this->assertSame('taler://pay/mock', $build['qr_code']['#attributes']['data-taler-pay-uri']);
    $this->assertArrayHasKey('wallet_hint', $build);
    $this->assertSame('taler_payments/payment_button', $build['#attached']['library'][0]);
    $this->assertSame('order-1', $build['#attached']['drupalSettings']['talerPaymentsCheckout']['orderId']);
    $this->assertSame('Configured Thank you!', $build['#attached']['drupalSettings']['talerPaymentsCheckout']['successMessage']);
    $this->assertSame('taler_payments_taler_support_meta', $build['#attached']['html_head'][0][1]);
    $this->assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsPaidStateForAlreadyPaidOrder(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-2')
      ->willReturn([
        'status' => 'paid',
        'summary' => 'Already paid',
        'amount' => 'EUR:5.00',
        'already_paid_order_id' => 'paid-123',
        'taler_pay_uri' => '',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-2');

    $this->assertArrayHasKey('status', $build);
    $this->assertSame('Configured Thank you!', (string) $build['status']['#value']);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsPaidStateWithoutRelatedOrderId(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-2b')
      ->willReturn([
        'status' => 'paid',
        'summary' => 'Already paid',
        'amount' => 'EUR:5.00',
        'taler_pay_uri' => '',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-2b');

    $this->assertArrayHasKey('status', $build);
    $this->assertSame('Configured Thank you!', (string) $build['status']['#value']);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsInvalidUriWarningForUnpaidNonTalerUri(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-invalid-uri')
      ->willReturn([
        'status' => 'unpaid',
        'summary' => 'Example summary',
        'amount' => 'EUR:1.00',
        'taler_pay_uri' => 'https://not-a-taler-uri',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-invalid-uri');

    $this->assertArrayHasKey('status_invalid_uri', $build);
    $this->assertArrayNotHasKey('pay_link', $build);
    $this->assertStringContainsString('Wallet URI is invalid', (string) $build['status_invalid_uri']['#value']);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsNotFoundState(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-not-found')
      ->willReturn([
        'status' => 'not_found',
        'summary' => 'Gone',
        'amount' => 'EUR:1.00',
        'taler_pay_uri' => '',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-not-found');

    $this->assertArrayHasKey('status', $build);
    $this->assertStringContainsString('no longer available', (string) $build['status']['#value']);
  }

  /**
   * @covers ::status
   */
  public function testStatusReturnsNotFoundForMissingCheckout(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')->with('missing')->willReturn(NULL);

    $controller = $this->createController($manager);
    $response = $controller->status('missing');

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('not_found', $data['status']);
    $this->assertTrue($data['is_final']);
  }

  /**
   * @covers ::status
   */
  public function testStatusReturnsPaidFinalState(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')->with('order-paid')->willReturn(['status' => 'paid']);

    $controller = $this->createController($manager);
    $response = $controller->status('order-paid');

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('paid', $data['status']);
    $this->assertTrue($data['is_final']);
  }

  /**
   * @covers ::checkout
   */
  public function testCheckoutBuildsFallbackUnknownState(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->with('order-unknown')
      ->willReturn([
        'status' => 'something-else',
        'summary' => 'Unknown',
        'amount' => 'EUR:1.00',
        'taler_pay_uri' => '',
      ]);

    $controller = $this->createController($manager);
    $build = $controller->checkout('order-unknown');

    $this->assertArrayHasKey('status', $build);
    $this->assertStringContainsString('currently unavailable', (string) $build['status']['#value']);
  }

  /**
   * @covers ::checkoutTitle
   */
  public function testCheckoutTitleUsesIntentTitleOrFallback(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->method('getCheckoutByOrderId')
      ->willReturnMap([
        ['a', ['title' => 'Order A']],
        ['b', ['title' => '   ']],
        ['c', NULL],
      ]);

    $controller = $this->createController($manager);

    $this->assertSame('Order A', $controller->checkoutTitle('a'));
    $this->assertSame('Checkout', $controller->checkoutTitle('b'));
    $this->assertSame('Checkout', $controller->checkoutTitle('c'));
  }

}
