<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taler_payments\Form\TalerPaymentsPublicTextSettingsForm;
use Drupal\taler_payments\PublicText\TalerPublicTextProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the public text customization settings form.
 *
 * @coversDefaultClass \Drupal\taler_payments\Form\TalerPaymentsPublicTextSettingsForm
 * @group taler_payments
 */
final class TalerPaymentsPublicTextSettingsFormTest extends TestCase {

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

  private function createForm(
    ConfigFactoryInterface $config_factory,
    TalerPublicTextProviderInterface $public_text_provider,
  ): TalerPaymentsPublicTextSettingsForm {
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);
    $form = new TalerPaymentsPublicTextSettingsForm($config_factory, $typed_config_manager, $public_text_provider);
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->createTranslationStub());
    return $form;
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormCreatesExpectedElementsAndMovesSubmitAction(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnMap([
      ['public_call_to_action', 'Do CTA'],
      ['public_thank_you_message', 'Thanks'],
      ['public_payment_button_cta', 'Pay now'],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('taler_payments.settings')
      ->willReturn($config);

    $public_text_provider = $this->createConfiguredMock(TalerPublicTextProviderInterface::class, [
      'getDefaultCallToAction' => 'Review your order details and complete payment with GNU Taler.',
      'getDefaultThankYouMessage' => 'Payment received. Thank you!',
      'getDefaultPaymentButtonCta' => 'Pay with Taler wallet in the browser',
    ]);

    $form = $this->createForm($config_factory, $public_text_provider);
    $built_form = $form->buildForm([], new FormState());

    $this->assertSame('details', $built_form['public_text_customization']['#type']);
    $this->assertSame('Do CTA', $built_form['public_text_customization']['public_call_to_action']['#default_value']);
    $this->assertSame('Review your order details and complete payment with GNU Taler.', $built_form['public_text_customization']['public_call_to_action']['#placeholder']);
    $this->assertSame('Thanks', $built_form['public_text_customization']['public_thank_you_message']['#default_value']);
    $this->assertSame('Payment received. Thank you!', $built_form['public_text_customization']['public_thank_you_message']['#placeholder']);
    $this->assertSame('Pay now', $built_form['public_text_customization']['public_payment_button_cta']['#default_value']);
    $this->assertSame('Pay with Taler wallet in the browser', $built_form['public_text_customization']['public_payment_button_cta']['#placeholder']);
    $this->assertArrayHasKey('actions', $built_form['public_text_customization']);
    $this->assertArrayHasKey('reset', $built_form['public_text_customization']['actions']);
    $this->assertSame('submit', $built_form['public_text_customization']['actions']['reset']['#type']);
    $this->assertSame('Reset to defaults', (string) $built_form['public_text_customization']['actions']['reset']['#value']);
    $this->assertArrayNotHasKey('actions', $built_form);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormTrimsInputs(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $public_text_provider = $this->createConfiguredMock(TalerPublicTextProviderInterface::class, [
      'getDefaultCallToAction' => 'Review your order details and complete payment with GNU Taler.',
      'getDefaultThankYouMessage' => 'Payment received. Thank you!',
      'getDefaultPaymentButtonCta' => 'Pay with Taler wallet in the browser',
    ]);
    $form = $this->createForm($config_factory, $public_text_provider);

    $form_state = new FormState();
    $form_state->setValue('public_call_to_action', '  CTA  ');
    $form_state->setValue('public_thank_you_message', '  Thank you  ');
    $form_state->setValue('public_payment_button_cta', '  Pay  ');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('CTA', $form_state->getValue('public_call_to_action'));
    $this->assertSame('Thank you', $form_state->getValue('public_thank_you_message'));
    $this->assertSame('Pay', $form_state->getValue('public_payment_button_cta'));
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPersistsValues(): void {
    $editable_config = $this->createMock(Config::class);
    $sets = [];
    $editable_config->expects($this->exactly(3))
      ->method('set')
      ->willReturnCallback(function (string $key, $value) use (&$sets, $editable_config) {
        $sets[] = [$key, $value];
        return $editable_config;
      });
    $editable_config->expects($this->once())->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('taler_payments.settings')
      ->willReturn($editable_config);

    $public_text_provider = $this->createConfiguredMock(TalerPublicTextProviderInterface::class, [
      'getDefaultCallToAction' => 'Review your order details and complete payment with GNU Taler.',
      'getDefaultThankYouMessage' => 'Payment received. Thank you!',
      'getDefaultPaymentButtonCta' => 'Pay with Taler wallet in the browser',
    ]);

    $form = $this->createForm($config_factory, $public_text_provider);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addStatus');
    $form->setMessenger($messenger);

    $form_state = new FormState();
    $form_state->setValue('public_call_to_action', 'A');
    $form_state->setValue('public_thank_you_message', 'B');
    $form_state->setValue('public_payment_button_cta', 'C');
    $form_array = [];
    $form->submitForm($form_array, $form_state);

    $this->assertSame([
      ['public_call_to_action', 'A'],
      ['public_thank_you_message', 'B'],
      ['public_payment_button_cta', 'C'],
    ], $sets);
  }

  /**
   * @covers ::resetToDefaultsSubmitForm
   */
  public function testResetToDefaultsSubmitFormClearsStoredCustomValues(): void {
    $editable_config = $this->createMock(Config::class);
    $clears = [];
    $editable_config->expects($this->exactly(3))
      ->method('clear')
      ->willReturnCallback(function (string $key) use (&$clears, $editable_config) {
        $clears[] = $key;
        return $editable_config;
      });
    $editable_config->expects($this->once())->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('taler_payments.settings')
      ->willReturn($editable_config);

    $public_text_provider = $this->createConfiguredMock(TalerPublicTextProviderInterface::class, [
      'getDefaultCallToAction' => 'Review your order details and complete payment with GNU Taler.',
      'getDefaultThankYouMessage' => 'Payment received. Thank you!',
      'getDefaultPaymentButtonCta' => 'Pay with Taler wallet in the browser',
    ]);

    $form = $this->createForm($config_factory, $public_text_provider);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addStatus');
    $form->setMessenger($messenger);

    $form_array = [];
    $form->resetToDefaultsSubmitForm($form_array, new FormState());

    $this->assertSame([
      'public_call_to_action',
      'public_thank_you_message',
      'public_payment_button_cta',
    ], $clears);
  }

}
