<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines settings form for the Taler Payments module.
 */
final class TalerPaymentsSettingsForm extends ConfigFormBase {

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
    return 'taler_payments_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('taler_payments.settings');

    $form['taler_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Taler Base URL'),
      '#description' => $this->t('Important: the Base URL must include the instance path (e.g. /instances/) and must start with https://<br>
Example: https://backend.demo.taler.net/instances/sandbox'),
      '#required' => TRUE,
      '#default_value' => $config->get('taler_base_url') ?? '',
      '#maxlength' => 2048,
      '#placeholder' => 'https://backend.demo.taler.net/instances/default',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $base_url = trim((string) $form_state->getValue('taler_base_url'));
    $form_state->setValue('taler_base_url', $base_url);

    if ($base_url === '') {
      $form_state->setErrorByName('taler_base_url', $this->t('Taler Base URL is required.'));
      return;
    }

    if (!UrlHelper::isValid($base_url, TRUE)) {
      $form_state->setErrorByName('taler_base_url', $this->t('Taler Base URL must be a valid absolute URL.'));
      return;
    }

    $parts = parse_url($base_url);
    $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';

    if ($scheme !== 'https' || $host === '') {
      $form_state->setErrorByName('taler_base_url', $this->t('Taler Base URL must start with https:// and include a host.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->set('taler_base_url', (string) $form_state->getValue('taler_base_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
