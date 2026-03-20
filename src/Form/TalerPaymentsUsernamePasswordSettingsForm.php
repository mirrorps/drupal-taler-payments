<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
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
   * Constructs the form object.
   */
  public function __construct(TalerCredentialEncryptor $credential_encryptor) {
    $this->credentialEncryptor = $credential_encryptor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('taler_payments.credential_encryptor'),
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

}
