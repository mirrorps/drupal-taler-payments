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
use Drupal\taler_payments\Form\TalerPaymentsAccessTokenSettingsForm;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use Drupal\taler_payments\Service\TalerClientManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests the access token settings form.
 *
 * @coversDefaultClass \Drupal\taler_payments\Form\TalerPaymentsAccessTokenSettingsForm
 * @group taler_payments
 */
final class TalerPaymentsAccessTokenSettingsFormTest extends TestCase {

  protected function setUp(): void {
    parent::setUp();

    // Drupal tracks "any form errors" as a global static flag on FormState.
    // Reset it so validation paths are isolated per test.
    $any_errors = new \ReflectionProperty(FormState::class, 'anyErrors');
    // $any_errors->setAccessible(TRUE);
    $any_errors->setValue(NULL, FALSE);
  }

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
   * Builds the settings form with minimal dependencies.
   *
   * The config factory mock.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *
   * The credential encryptor mock.
   * @param \Drupal\taler_payments\Security\TalerCredentialEncryptor $credential_encryptor
   *
   * The Taler client manager mock.
   * @param \Drupal\taler_payments\Service\TalerClientManager $taler_client_manager
   *
   * The settings form instance.
   * @return \Drupal\taler_payments\Form\TalerPaymentsAccessTokenSettingsForm
   */
  private function createForm(
    ConfigFactoryInterface $config_factory,
    TalerCredentialEncryptor $credential_encryptor,
    TalerClientManager $taler_client_manager,
  ): TalerPaymentsAccessTokenSettingsForm {
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    $form = new TalerPaymentsAccessTokenSettingsForm(
      $config_factory,
      $typed_config_manager,
      $credential_encryptor,
      $taler_client_manager,
    );
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->createTranslationStub());

    return $form;
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $this->assertSame('taler_payments_access_token_settings_form', $form->getFormId());
  }

  /**
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $editable_config_names = (new \ReflectionMethod($form, 'getEditableConfigNames'))->invoke($form);
    $this->assertSame(['taler_payments.settings'], $editable_config_names);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormCreatesExpectedElementsAndMovesSubmitAction(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $built_form = $form->buildForm([], $form_state);

    $this->assertSame('details', $built_form['access_token']['#type']);
    $this->assertTrue($built_form['access_token']['#open']);
    $this->assertSame('textfield', $built_form['access_token']['taler_access_token']['#type']);
    $this->assertTrue($built_form['access_token']['taler_access_token']['#required']);
    $this->assertSame(4096, $built_form['access_token']['taler_access_token']['#maxlength']);
    $this->assertSame('', $built_form['access_token']['taler_access_token']['#default_value']);
    $this->assertSame('off', $built_form['access_token']['taler_access_token']['#attributes']['autocomplete']);
    $this->assertSame('Bearer secret-token:sandbox', $built_form['access_token']['taler_access_token']['#placeholder']);
    $this->assertArrayHasKey('actions', $built_form['access_token']);
    $this->assertArrayHasKey('delete', $built_form['access_token']['actions']);
    $this->assertSame('submit', $built_form['access_token']['actions']['delete']['#type']);
    $this->assertSame('Delete', (string) $built_form['access_token']['actions']['delete']['#value']);
    $this->assertArrayNotHasKey('actions', $built_form);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsEmptyAccessTokenAndTrimsInput(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->never())
                         ->method('validateAccessTokenCredentials');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('taler_access_token', '   ');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('', $form_state->getValue('taler_access_token'));
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('taler_access_token', $errors);
    $this->assertStringContainsString('required', (string) $errors['taler_access_token']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsWhenEncryptionIsUnavailable(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(FALSE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->never())
                         ->method('validateAccessTokenCredentials');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('taler_access_token', 'Bearer valid-token');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('taler_access_token', $errors);
    $this->assertStringContainsString('encryption is not available', (string) $errors['taler_access_token']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormAcceptsValidTokenAndCallsClientManager(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->once())
                         ->method('validateAccessTokenCredentials')
                         ->with('Bearer valid-token');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('taler_access_token', '  Bearer valid-token  ');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('Bearer valid-token', $form_state->getValue('taler_access_token'));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsWhenTokenAuthenticationFails(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->once())
                         ->method('validateAccessTokenCredentials')
                         ->with('Bearer invalid-token')
                         ->willThrowException(new \RuntimeException('Auth failed'));

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('taler_access_token', 'Bearer invalid-token');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('taler_access_token', $errors);
    $this->assertStringContainsString('Could not authenticate with Taler', (string) $errors['taler_access_token']);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormEncryptsAndPersistsAccessToken(): void {
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('encrypt')
                         ->with('Bearer valid-token')
                         ->willReturn('encrypted-access-token');

    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
                    ->method('set')
                    ->with('access_token', 'encrypted-access-token')
                    ->willReturnSelf();
    $editable_config->expects($this->once())
                    ->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
                   ->method('getEditable')
                   ->with('taler_payments.settings')
                   ->willReturn($editable_config);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
              ->method('addStatus');
    $form->setMessenger($messenger);

    $form_state = new FormState();
    $form_state->setValue('taler_access_token', 'Bearer valid-token');

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * @covers ::deleteAccessTokenSubmitForm
   */
  public function testDeleteAccessTokenSubmitFormClearsStoredAccessToken(): void {
    $editable_config = $this->createMock(Config::class);
    $editable_config->expects($this->once())
      ->method('clear')
      ->with('access_token')
      ->willReturnSelf();
    $editable_config->expects($this->once())
      ->method('save');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('taler_payments.settings')
      ->willReturn($editable_config);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addStatus');
    $form->setMessenger($messenger);

    $form_array = [];
    $form->deleteAccessTokenSubmitForm($form_array, new FormState());
  }

}
