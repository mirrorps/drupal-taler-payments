<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Plugin\Block;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Plugin\Block\TalerPaymentButtonBlock;
use Drupal\taler_payments\TalerPaymentButtonBuilderInterface;
use Drupal\taler_payments\Validation\TalerAmountInputValidatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\taler_payments\Plugin\Block\TalerPaymentButtonBlock
 *
 * @group taler_payments
 */
final class TalerPaymentButtonBlockTest extends TestCase {

  /**
   * @return array<string, mixed>
   */
  private function pluginDefinition(): array {
    return [
      'provider' => 'taler_payments',
      'admin_label' => 'Taler payment button',
    ];
  }

  private function createTranslationStub(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn (string $string, array $args = [], array $options = []): TranslatableMarkup => new TranslatableMarkup($string, $args, $options, $translation));

    return $translation;
  }

  /**
   * @param array<string, mixed> $configuration
   */
  private function createBlock(
    array $configuration,
    ?TalerPaymentButtonBuilderInterface $builder = NULL,
    ?TalerAmountInputValidatorInterface $validator = NULL,
  ): TalerPaymentButtonBlock {
    $builder ??= $this->createMock(TalerPaymentButtonBuilderInterface::class);
    $validator ??= $this->createMock(TalerAmountInputValidatorInterface::class);

    $block = new TalerPaymentButtonBlock(
      $configuration,
      'taler_payment_button',
      $this->pluginDefinition(),
      $builder,
      $validator,
    );
    $block->setStringTranslation($this->createTranslationStub());

    return $block;
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationContainsButtonSummaryAmount(): void {
    $block = $this->createBlock([]);
    $config = $block->getConfiguration();

    $this->assertSame('Pay with Taler', $config['button_text']);
    $this->assertSame('', $config['summary']);
    $this->assertSame('EUR:1.00', $config['amount']);
  }

  /**
   * @covers ::blockForm
   */
  public function testBlockFormContainsFieldsWithDefaults(): void {
    $block = $this->createBlock([
      'button_text' => 'Custom',
      'summary' => 'A note',
      'amount' => 'KUDOS:0.11',
    ]);
    $form = $block->blockForm([], new FormState());

    $this->assertSame('textfield', $form['button_text']['#type']);
    $this->assertSame('Custom', $form['button_text']['#default_value']);
    $this->assertSame('textarea', $form['summary']['#type']);
    $this->assertSame('A note', $form['summary']['#default_value']);
    $this->assertSame('textfield', $form['amount']['#type']);
    $this->assertSame('KUDOS:0.11', $form['amount']['#default_value']);
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidateSetsErrorWhenValidatorReturnsMessage(): void {
    $error = new TranslatableMarkup('Invalid amount.', [], [], $this->createTranslationStub());
    $validator = $this->createMock(TalerAmountInputValidatorInterface::class);
    $validator->expects($this->once())
      ->method('validate')
      ->with('bad')
      ->willReturn($error);

    $block = $this->createBlock([], NULL, $validator);
    $form_state = new FormState();
    $form_state->setValue('amount', 'bad');

    $block->blockValidate([], $form_state);

    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * @covers ::blockValidate
   */
  public function testBlockValidateDoesNotSetErrorWhenValidatorReturnsNull(): void {
    $validator = $this->createMock(TalerAmountInputValidatorInterface::class);
    $validator->method('validate')->willReturn(NULL);

    $block = $this->createBlock([], NULL, $validator);
    $form_state = new FormState();
    $form_state->setValue('amount', 'EUR:1.00');

    $block->blockValidate([], $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::blockSubmit
   */
  public function testBlockSubmitStoresTrimmedValues(): void {
    $block = $this->createBlock([]);
    $form_state = new FormState();
    $form_state->setValues([
      'button_text' => 'Go',
      'summary' => "Text",
      'amount' => '  EUR:2.00  ',
    ]);

    $block->blockSubmit([], $form_state);

    $config = $block->getConfiguration();
    $this->assertSame('Go', $config['button_text']);
    $this->assertSame('Text', $config['summary']);
    $this->assertSame('EUR:2.00', $config['amount']);
  }

  /**
   * @covers ::build
   */
  public function testBuildDelegatesToButtonBuilder(): void {
    $expected = ['#type' => 'markup', '#markup' => 'stub'];
    $builder = $this->createMock(TalerPaymentButtonBuilderInterface::class);
    $builder->expects($this->once())
      ->method('build')
      ->with($this->callback(function (array $configuration): bool {
        return isset($configuration['button_text'], $configuration['amount']);
      }))
      ->willReturn($expected);

    $block = $this->createBlock([
      'button_text' => 'Pay',
      'summary' => '',
      'amount' => 'EUR:1.00',
    ], $builder);

    $this->assertSame($expected, $block->build());
  }

  /**
   * @covers ::create
   */
  public function testCreatePullsServicesFromContainer(): void {
    $builder = $this->createMock(TalerPaymentButtonBuilderInterface::class);
    $builder->method('build')->willReturn(['#markup' => 'from-builder']);
    $validator = $this->createMock(TalerAmountInputValidatorInterface::class);

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['taler_payments.payment_button_builder', $builder],
        ['taler_payments.amount_input_validator', $validator],
      ]);

    $block = TalerPaymentButtonBlock::create(
      $container,
      [],
      'taler_payment_button',
      $this->pluginDefinition(),
    );
    $block->setStringTranslation($this->createTranslationStub());

    $this->assertInstanceOf(TalerPaymentButtonBlock::class, $block);
    $this->assertSame(['#markup' => 'from-builder'], $block->build());
  }

}
