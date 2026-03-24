<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Checkout\TalerCheckoutManagerInterface;
use Drupal\taler_payments\Controller\TalerCheckoutController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

  private function createController(?TalerCheckoutManagerInterface $manager = NULL): TalerCheckoutController {
    $manager ??= $this->createMock(TalerCheckoutManagerInterface::class);
    $controller = new TalerCheckoutController($manager);
    $controller->setStringTranslation($this->createTranslationStub());
    return $controller;
  }

  /**
   * @covers ::create
   */
  public function testCreateBuildsControllerFromContainer(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->with('taler_payments.checkout_manager')
      ->willReturn($manager);

    $controller = TalerCheckoutController::create($container);
    $this->assertInstanceOf(TalerCheckoutController::class, $controller);
  }

  /**
   * @covers ::create
   */
  public function testCreateThrowsForInvalidServiceType(): void {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->with('taler_payments.checkout_manager')
      ->willReturn(new \stdClass());

    $this->expectException(\InvalidArgumentException::class);
    TalerCheckoutController::create($container);
  }

  /**
   * @covers ::start
   */
  public function testStartRejectsMissingAmount(): void {
    $controller = $this->createController();
    $request = new Request(query: ['title' => 'My order']);

    $this->expectException(AccessDeniedHttpException::class);
    $controller->start($request);
  }

  /**
   * @covers ::start
   */
  public function testStartRedirectsToCheckoutPageWithTrimmedInputs(): void {
    $manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $manager->expects($this->once())
      ->method('beginCheckout')
      ->with('Title', 'EUR:3.00', 'Summary')
      ->willReturn(['order_id' => 'ord-123']);

    $controller = $this->createController($manager);
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->expects($this->once())
      ->method('generateFromRoute')
      ->with('taler_payments.checkout_page', ['order_id' => 'ord-123'], [], FALSE)
      ->willReturn('/checkout/ord-123');
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);

    $request = new Request(query: [
      'title' => '  Title  ',
      'amount' => ' EUR:3.00 ',
      'summary' => ' Summary ',
    ]);

    $response = $controller->start($request);
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertStringContainsString('ord-123', $response->getTargetUrl());
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
    $this->assertSame('Example summary', $build['meta']['summary']['value']['#value']);
    $this->assertSame('EUR:1.00', $build['meta']['amount']['value']['#value']);
    $this->assertSame('taler://pay/mock', $build['pay_link']['#attributes']['href']);
    $this->assertArrayHasKey('wallet_hint', $build);
    $this->assertSame('taler_payments/payment_button', $build['#attached']['library'][0]);
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
    $this->assertStringContainsString('paid-123', (string) $build['status']['#value']);
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
    $this->assertStringContainsString('Payment already completed.', (string) $build['status']['#value']);
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
