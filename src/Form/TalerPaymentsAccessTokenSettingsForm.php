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
 * Defines access token settings form for Taler Payments.
 */
final class TalerPaymentsAccessTokenSettingsForm extends ConfigFormBase {

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
    return 'taler_payments_access_token_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['access_token'] = [
      '#type' => 'details',
      '#title' => $this->t('Access Token'),
      '#open' => TRUE,
    ];

    $form['access_token']['taler_access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('Access token for the Taler Merchant Backend.<br>Save the full value used in the HTTP Authorization header, including the prefix (example: Bearer secret-token:sandbox).<br>The access token will be first encrypted and then stored in the database.'),
      '#required' => TRUE,
      '#maxlength' => 4096,
      '#default_value' => '',
      '#attributes' => [
        'autocomplete' => 'off',
      ],
      '#placeholder' => 'Bearer secret-token:sandbox',
    ];

    $form = parent::buildForm($form, $form_state);

    // Keep the submit button inside the details section so it is hidden when
    // the section is collapsed.
    if (isset($form['actions']['submit'])) {
      $form['access_token']['actions'] = $form['actions'];
      unset($form['actions']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $access_token = trim((string) $form_state->getValue('taler_access_token'));
    $form_state->setValue('taler_access_token', $access_token);

    if ($access_token === '') {
      $form_state->setErrorByName('taler_access_token', $this->t('Access Token is required.'));
    }

    if (!$this->credentialEncryptor->isEncryptionAvailable()) {
      $form_state->setErrorByName('taler_access_token', $this->t('Access token encryption is not available on this server.'));
      return;
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    try {
      $this->talerClientManager->validateAccessTokenCredentials($access_token);
    }
    catch (\Throwable) {
      $form_state->setErrorByName(
        'taler_access_token',
        $this->t('Could not authenticate with Taler using the provided access token. Please verify the token and base URL.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $encrypted_access_token = $this->credentialEncryptor->encrypt((string) $form_state->getValue('taler_access_token'));

    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->set('access_token', $encrypted_access_token)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
