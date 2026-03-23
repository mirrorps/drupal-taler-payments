<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit;

use Drupal\Core\Url;
use Drupal\taler_payments\TalerPaymentButtonBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taler_payments\TalerPaymentButtonBuilder
 *
 * @group taler_payments
 */
final class TalerPaymentButtonBuilderTest extends TestCase {

  /**
   * @covers ::build
   */
  public function testBuildOutputsContainerLinkAndLibraryOnly(): void {
    $builder = new TalerPaymentButtonBuilder();
    $build = $builder->build([
      'label' => 'Donation checkout',
      'button_text' => 'Pay',
      'summary' => 'Order summary',
      'amount' => 'EUR:1.00',
    ]);

    $this->assertSame('container', $build['#type']);
    $this->assertArrayNotHasKey('summary', $build);
    $this->assertArrayNotHasKey('amount', $build);
    $this->assertSame('Pay', $build['link']['#title']);
    $this->assertInstanceOf(Url::class, $build['link']['#url']);
    $this->assertSame('taler_payments.checkout_start', $build['link']['#url']->getRouteName());
    $this->assertSame('Donation checkout', $build['link']['#url']->getOption('query')['title']);
    $this->assertSame('EUR:1.00', $build['link']['#url']->getOption('query')['amount']);
    $this->assertSame('Order summary', $build['link']['#url']->getOption('query')['summary']);
    $this->assertSame('true', $build['link']['#attributes']['data-disable-once']);
    $this->assertSame(['taler_payments/payment_button'], $build['#attached']['library']);
  }

  /**
   * @covers ::build
   */
  public function testBuildDoesNotOutputSummaryOrAmountOnFrontEnd(): void {
    $builder = new TalerPaymentButtonBuilder();
    $build = $builder->build([
      'button_text' => 'Pay',
      'summary' => "Line1\n<script>x</script>",
      'amount' => 'EUR:1.00<b>',
    ]);

    $this->assertArrayNotHasKey('summary', $build);
    $this->assertArrayNotHasKey('amount', $build);
  }

  /**
   * @covers ::build
   */
  public function testBuildUsesEmptyButtonTextWhenMissing(): void {
    $builder = new TalerPaymentButtonBuilder();
    $build = $builder->build([]);

    $this->assertSame('', $build['link']['#title']);
    $this->assertSame('', $build['link']['#url']->getOption('query')['title']);
  }

}
