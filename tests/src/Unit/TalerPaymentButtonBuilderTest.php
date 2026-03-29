<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit;

use Drupal\Core\Form\FormBuilderInterface;
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
  public function testBuildDelegatesToCheckoutStartFormWithNormalizedConfiguration(): void {
    $expected_build = ['#type' => 'form', '#stub' => 'checkout-start'];
    $form_builder = $this->createMock(FormBuilderInterface::class);
    $form_builder->expects($this->once())
      ->method('getForm')
      ->with(
        '\Drupal\taler_payments\Form\TalerCheckoutStartForm',
        [
          'button_text' => 'Pay',
          'title' => 'Donation checkout',
          'amount' => 'EUR:1.00',
          'summary' => 'Order summary',
        ],
      )
      ->willReturn($expected_build);

    $builder = new TalerPaymentButtonBuilder($form_builder);
    $build = $builder->build([
      'label' => 'Donation checkout',
      'button_text' => 'Pay',
      'summary' => 'Order summary',
      'amount' => 'EUR:1.00',
    ]);

    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::build
   */
  public function testBuildDoesNotOutputSummaryOrAmountOnFrontEnd(): void {
    $form_builder = $this->createMock(FormBuilderInterface::class);
    $form_builder->expects($this->once())
      ->method('getForm')
      ->with(
        '\Drupal\taler_payments\Form\TalerCheckoutStartForm',
        $this->callback(static function (array $configuration): bool {
          return ($configuration['summary'] ?? NULL) === "Line1\n<script>x</script>"
            && ($configuration['amount'] ?? NULL) === 'EUR:1.00<b>';
        }),
      )
      ->willReturn(['#type' => 'form']);

    $builder = new TalerPaymentButtonBuilder($form_builder);
    $build = $builder->build([
      'button_text' => 'Pay',
      'summary' => "Line1\n<script>x</script>",
      'amount' => 'EUR:1.00<b>',
    ]);

    $this->assertSame('form', $build['#type']);
  }

  /**
   * @covers ::build
   */
  public function testBuildUsesEmptyButtonTextWhenMissing(): void {
    $form_builder = $this->createMock(FormBuilderInterface::class);
    $form_builder->expects($this->once())
      ->method('getForm')
      ->with('\Drupal\taler_payments\Form\TalerCheckoutStartForm', [
        'button_text' => '',
        'title' => '',
        'amount' => '',
        'summary' => '',
      ])
      ->willReturn(['#type' => 'form']);

    $builder = new TalerPaymentButtonBuilder($form_builder);
    $build = $builder->build([]);

    $this->assertSame('form', $build['#type']);
  }

}
