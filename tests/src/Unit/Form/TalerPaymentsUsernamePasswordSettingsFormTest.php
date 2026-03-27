<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taler_payments\Form\TalerPaymentsUsernamePasswordSettingsForm;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use Drupal\taler_payments\Service\TalerClientManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests the username/password settings form.
 *
 * @coversDefaultClass \Drupal\taler_payments\Form\TalerPaymentsUsernamePasswordSettingsForm
 * @group taler_payments
 */
final class TalerPaymentsUsernamePasswordSettingsFormTest extends TestCase {

  protected function setUp(): void {
    parent::setUp();

    // Drupal tracks "any form errors" as a global static flag on FormState.
    // Reset it so validation paths are isolated per test.
    $any_errors = new \ReflectionProperty(FormState::class, 'anyErrors');
    $any_errors->setValue(NULL, FALSE);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
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
   * @return \Drupal\taler_payments\Form\TalerPaymentsUsernamePasswordSettingsForm
   */
  private function createForm(
    ConfigFactoryInterface $config_factory,
    TalerCredentialEncryptor $credential_encryptor,
    TalerClientManager $taler_client_manager,
  ): TalerPaymentsUsernamePasswordSettingsForm {
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    $form = new TalerPaymentsUsernamePasswordSettingsForm(
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

    $this->assertSame('taler_payments_username_password_settings_form', $form->getFormId());
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
    $editable_config = $this->createMock(Config::class);
    $editable_config->method('get')
                    ->willReturnMap([
                      ['instance_id', 'my-instance'],
                      ['username', 'my-user'],
                    ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
                   ->method('getEditable')
                   ->with('taler_payments.settings')
                   ->willReturn($editable_config);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $built_form = $form->buildForm([], $form_state);

    $this->assertSame('details', $built_form['username_password']['#type']);
    $this->assertTrue($built_form['username_password']['#open']);
    $this->assertSame('textfield', $built_form['username_password']['instance_id']['#type']);
    $this->assertTrue($built_form['username_password']['instance_id']['#required']);
    $this->assertSame('my-instance', $built_form['username_password']['instance_id']['#default_value']);
    $this->assertSame(255, $built_form['username_password']['instance_id']['#maxlength']);
    $this->assertSame('textfield', $built_form['username_password']['username']['#type']);
    $this->assertTrue($built_form['username_password']['username']['#required']);
    $this->assertSame('my-user', $built_form['username_password']['username']['#default_value']);
    $this->assertSame('password', $built_form['username_password']['password']['#type']);
    $this->assertTrue($built_form['username_password']['password']['#required']);
    $this->assertSame('new-password', $built_form['username_password']['password']['#attributes']['autocomplete']);
    $this->assertArrayHasKey('actions', $built_form['username_password']);
    $this->assertArrayHasKey('delete', $built_form['username_password']['actions']);
    $this->assertSame('submit', $built_form['username_password']['actions']['delete']['#type']);
    $this->assertSame('Delete', (string) $built_form['username_password']['actions']['delete']['#value']);
    $this->assertArrayNotHasKey('actions', $built_form);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsEmptyCredentialsAndTrimsInput(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->never())
                         ->method('validateUsernamePasswordCredentials');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('instance_id', '   ');
    $form_state->setValue('username', '   ');
    $form_state->setValue('password', '');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('', $form_state->getValue('instance_id'));
    $this->assertSame('', $form_state->getValue('username'));
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('instance_id', $errors);
    $this->assertArrayHasKey('username', $errors);
    $this->assertArrayHasKey('password', $errors);
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
                         ->method('validateUsernamePasswordCredentials');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('instance_id', 'inst');
    $form_state->setValue('username', 'user');
    $form_state->setValue('password', 'secret');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('password', $errors);
    $this->assertStringContainsString('encryption is not available', (string) $errors['password']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormAcceptsValidCredentialsAndCallsClientManager(): void {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->once())
                         ->method('validateUsernamePasswordCredentials')
                         ->with('inst', 'user', 'secret');

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('instance_id', '  inst  ');
    $form_state->setValue('username', '  user  ');
    $form_state->setValue('password', 'secret');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame('inst', $form_state->getValue('instance_id'));
    $this->assertSame('user', $form_state->getValue('username'));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsWhenAuthenticationFails(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
           ->method('error');

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->expects($this->once())
                   ->method('get')
                   ->with('taler_payments')
                   ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $logger_factory);
    \Drupal::setContainer($container);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('isEncryptionAvailable')
                         ->willReturn(TRUE);

    $taler_client_manager = $this->createMock(TalerClientManager::class);
    $taler_client_manager->expects($this->once())
                         ->method('validateUsernamePasswordCredentials')
                         ->with('inst', 'user', 'wrong')
                         ->willThrowException(new \RuntimeException('Auth failed'));

    $form = $this->createForm($config_factory, $credential_encryptor, $taler_client_manager);

    $form_state = new FormState();
    $form_state->setValue('instance_id', 'inst');
    $form_state->setValue('username', 'user');
    $form_state->setValue('password', 'wrong');
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('username', $errors);
    $this->assertStringContainsString('Could not authenticate with Taler', (string) $errors['username']);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormEncryptsAndPersistsCredentials(): void {
    $credential_encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $credential_encryptor->expects($this->once())
                         ->method('encrypt')
                         ->with('plain-secret')
                         ->willReturn('encrypted-password');

    $editable_config = $this->createMock(Config::class);
    $sets = [];
    $editable_config->expects($this->exactly(3))
                    ->method('set')
                    ->willReturnCallback(function (string $key, $value) use (&$sets, $editable_config) {
                      $sets[] = [$key, $value];
                      return $editable_config;
                    });
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
    $form_state->setValue('instance_id', 'my-inst');
    $form_state->setValue('username', 'my-user');
    $form_state->setValue('password', 'plain-secret');

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    $this->assertSame([
      ['instance_id', 'my-inst'],
      ['username', 'my-user'],
      ['password', 'encrypted-password'],
    ], $sets);
  }

  /**
   * @covers ::deleteUsernamePasswordSubmitForm
   */
  public function testDeleteUsernamePasswordSubmitFormClearsStoredCredentials(): void {
    $editable_config = $this->createMock(Config::class);
    $clears = [];
    $editable_config->expects($this->exactly(3))
      ->method('clear')
      ->willReturnCallback(function (string $key) use (&$clears, $editable_config) {
        $clears[] = $key;
        return $editable_config;
      });
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
    $form->deleteUsernamePasswordSubmitForm($form_array, new FormState());

    $this->assertSame(['instance_id', 'username', 'password'], $clears);
  }

}
