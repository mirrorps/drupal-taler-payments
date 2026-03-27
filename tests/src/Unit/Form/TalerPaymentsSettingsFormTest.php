<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Form\TalerPaymentsSettingsForm;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Taler payments settings form.
 *
 * @coversDefaultClass \Drupal\taler_payments\Form\TalerPaymentsSettingsForm
 * @group taler_payments
 */
final class TalerPaymentsSettingsFormTest extends TestCase {

  /**
   * Provides a lightweight translation stub for form string building.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface
   *   A translation interface stub.
   */
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

  /**
   * Builds the settings form with mocked dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory mock.
   *
   * @return \Drupal\taler_payments\Form\TalerPaymentsSettingsForm
   *   The settings form instance.
   */
  private function createForm(ConfigFactoryInterface $config_factory): TalerPaymentsSettingsForm {
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    $form = new TalerPaymentsSettingsForm($config_factory, $typed_config_manager);
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->createTranslationStub());

    return $form;
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $form = $this->createForm($config_factory);

    $this->assertSame('taler_payments_settings_form', $form->getFormId());
  }

  /**
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $form = $this->createForm($config_factory);

    $editable_config_names = (new \ReflectionMethod($form, 'getEditableConfigNames'))->invoke($form);
    $this->assertSame(['taler_payments.settings'], $editable_config_names);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormUsesConfiguredDefaultValue(): void {
    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
                    ->method('get')
                    ->with('taler_base_url')
                    ->willReturn('https://backend.demo.taler.net/instances/default');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
                   ->method('getEditable')
                   ->with('taler_payments.settings')
                   ->willReturn($editable_config);

    $form = $this->createForm($config_factory);
    $form_state = new FormState();

    $built_form = $form->buildForm([], $form_state);

    $this->assertSame('url', $built_form['base_url']['taler_base_url']['#type']);
    $this->assertTrue($built_form['base_url']['taler_base_url']['#required']);
    $this->assertSame('https://backend.demo.taler.net/instances/default', $built_form['base_url']['taler_base_url']['#default_value']);
    $this->assertArrayHasKey('delete', $built_form['base_url']['actions']);
    $this->assertSame('submit', $built_form['base_url']['actions']['delete']['#type']);
    $this->assertSame('Delete', (string) $built_form['base_url']['actions']['delete']['#value']);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormFallsBackToEmptyDefaultValue(): void {
    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
                    ->method('get')
                    ->with('taler_base_url')
                    ->willReturn(NULL);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
                   ->method('getEditable')
                   ->with('taler_payments.settings')
                   ->willReturn($editable_config);

    $form = $this->createForm($config_factory);
    $form_state = new FormState();

    $built_form = $form->buildForm([], $form_state);

    $this->assertSame('', $built_form['base_url']['taler_base_url']['#default_value']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsInvalidValues(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $form = $this->createForm($config_factory);

    $cases = [
      [
        'value' => '',
        'expected_error' => 'Taler Base URL is required.',
      ],
      [
        'value' => 'not-a-url',
        'expected_error' => 'Taler Base URL must be a valid absolute URL.',
      ],
      [
        'value' => 'http://backend.demo.taler.net/instances/default',
        'expected_error' => 'Taler Base URL must start with https:// and include a host.',
      ],
    ];

    foreach ($cases as $case) {
      $form_state = new FormState();
      $form_state->setValue('taler_base_url', $case['value']);

      $form_array = [];
      $form->validateForm($form_array, $form_state);

      $errors = $form_state->getErrors();
      $this->assertArrayHasKey('taler_base_url', $errors);
      $this->assertStringContainsString($case['expected_error'], (string) $errors['taler_base_url']);
    }
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormAcceptsHttpsUrlAndTrimsInput(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $form = $this->createForm($config_factory);

    $form_state = new FormState();
    $form_state->setValue('taler_base_url', '  https://backend.demo.taler.net/instances/default  ');

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('https://backend.demo.taler.net/instances/default', $form_state->getValue('taler_base_url'));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPersistsConfiguredBaseUrl(): void {
    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
                    ->method('set')
                    ->with('taler_base_url', 'https://backend.demo.taler.net/instances/default')
                    ->willReturnSelf();
    $editable_config->expects($this->once())
                    ->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
                   ->method('getEditable')
                   ->with('taler_payments.settings')
                   ->willReturn($editable_config);

    $form = $this->createForm($config_factory);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
              ->method('addStatus');
    $form->setMessenger($messenger);

    $form_state = new FormState();
    $form_state->setValue('taler_base_url', 'https://backend.demo.taler.net/instances/default');

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * @covers ::deleteBaseUrlSubmitForm
   */
  public function testDeleteBaseUrlSubmitFormClearsConfiguredBaseUrl(): void {
    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
      ->method('clear')
      ->with('taler_base_url')
      ->willReturnSelf();
    $editable_config->expects($this->once())
      ->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('taler_payments.settings')
      ->willReturn($editable_config);

    $form = $this->createForm($config_factory);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addStatus');
    $form->setMessenger($messenger);

    $form_array = [];
    $form->deleteBaseUrlSubmitForm($form_array, new FormState());
  }

}
