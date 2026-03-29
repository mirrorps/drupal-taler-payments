<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use Drupal\taler_payments\Service\TalerClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines username/password settings form for Taler Payments.
 */
final class TalerPaymentsUsernamePasswordSettingsForm extends ConfigFormBase {

  /**
   * The credential encryptor service.
   */
  private TalerCredentialEncryptor $credentialEncryptor;

  /**
   * The Taler client manager service.
   */
  private TalerClientManager $talerClientManager;

  /**
   * Constructs the form object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    TalerCredentialEncryptor $credential_encryptor,
    TalerClientManager $taler_client_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->credentialEncryptor = $credential_encryptor;
    $this->talerClientManager = $taler_client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $container->get('config.typed');
    /** @var \Drupal\taler_payments\Security\TalerCredentialEncryptor $credential_encryptor */
    $credential_encryptor = $container->get('taler_payments.credential_encryptor');
    /** @var \Drupal\taler_payments\Service\TalerClientManager $taler_client_manager */
    $taler_client_manager = $container->get('taler_payments.client_manager');

    return new static(
      $config_factory,
      $typed_config_manager,
      $credential_encryptor,
      $taler_client_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['taler_payments.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'taler_payments_username_password_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('taler_payments.settings');

    $form['username_password'] = [
      '#type' => 'details',
      '#title' => $this->t('Username & Password'),
      '#open' => TRUE,
    ];

    $form['username_password']['instance_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instance ID'),
      '#description' => $this->t('Required when authenticated with Username & Password.<br>The instance ID specifies which Taler Merchant Backend instance to authenticate against.'),
      '#required' => TRUE,
      '#default_value' => $config->get('instance_id') ?? '',
      '#maxlength' => 255,
    ];

    $form['username_password']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Username for the Taler Merchant Backend instance.'),
      '#required' => TRUE,
      '#default_value' => $config->get('username') ?? '',
      '#maxlength' => 255,
    ];

    $form['username_password']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Password for the Taler Merchant Backend instance.<br>The password will be first encrypted and then stored in the database.'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];

    $form = parent::buildForm($form, $form_state);

    // Keep the submit button inside the details section so it is hidden when
    // the section is collapsed.
    if (isset($form['actions']['submit'])) {
      $form['username_password']['actions'] = $form['actions'];
      $form['username_password']['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['::deleteUsernamePasswordSubmitForm'],
        '#validate' => [],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'onclick' => "return confirm('Are you sure you want to delete these values?');",
        ],
      ];
      unset($form['actions']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $instance_id = trim((string) $form_state->getValue('instance_id'));
    $username = trim((string) $form_state->getValue('username'));
    $password = (string) $form_state->getValue('password');

    $form_state->setValue('instance_id', $instance_id);
    $form_state->setValue('username', $username);

    if ($instance_id === '') {
      $form_state->setErrorByName('instance_id', $this->t('Instance ID is required.'));
    }

    if ($username === '') {
      $form_state->setErrorByName('username', $this->t('Username is required.'));
    }

    if ($password === '') {
      $form_state->setErrorByName('password', $this->t('Password is required.'));
    }

    if (!$this->credentialEncryptor->isEncryptionAvailable()) {
      $form_state->setErrorByName('password', $this->t('Password encryption is not available on this server.'));
      return;
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    try {
      $this->talerClientManager->validateUsernamePasswordCredentials(
        $instance_id,
        $username,
        $password,
      );
    }
    catch (\Throwable $e) {

      \Drupal::logger('taler_payments')->error(
        'Taler auth validation failed for instance "@instance". Error type: @type',
        [
          '@instance' => $instance_id,
          '@type' => get_debug_type($e),
        ]
      );

      $form_state->setErrorByName(
        'username',
        $this->t('Could not authenticate with Taler using the provided Instance ID, username, and password. Please verify the credentials and base URL.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $encrypted_password = $this->credentialEncryptor->encrypt((string) $form_state->getValue('password'));

    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->set('instance_id', (string) $form_state->getValue('instance_id'))
      ->set('username', (string) $form_state->getValue('username'))
      ->set('password', $encrypted_password)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Clears saved username/password credentials configuration.
   */
  public function deleteUsernamePasswordSubmitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->clear('instance_id')
      ->clear('username')
      ->clear('password')
      ->save();

    $this->messenger()->addStatus($this->t('Username and password credentials have been deleted.'));
  }

}
