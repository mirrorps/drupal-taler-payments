<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taler_payments\PublicText\TalerPublicTextProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines public text customization settings form for Taler Payments.
 */
final class TalerPaymentsPublicTextSettingsForm extends ConfigFormBase {

  /**
   * The public text provider.
   */
  private readonly TalerPublicTextProviderInterface $publicTextProvider;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    TalerPublicTextProviderInterface $public_text_provider,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->publicTextProvider = $public_text_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $container->get('config.typed');
    /** @var \Drupal\taler_payments\PublicText\TalerPublicTextProviderInterface $public_text_provider */
    $public_text_provider = $container->get('taler_payments.public_text_provider');

    return new static(
      $config_factory,
      $typed_config_manager,
      $public_text_provider,
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
    return 'taler_payments_public_text_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory->getEditable('taler_payments.settings');

    $form['public_text_customization'] = [
      '#type' => 'details',
      '#title' => $this->t('Public Text Customization'),
      '#open' => TRUE,
    ];

    $form['public_text_customization']['public_call_to_action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call-To-Action'),
      '#description' => $this->t('Shown on the payment card as title. Leave empty to use the default text.'),
      '#default_value' => (string) ($config->get('public_call_to_action') ?? ''),
      '#placeholder' => $this->publicTextProvider->getDefaultCallToAction(),
      '#maxlength' => 255,
    ];

    $form['public_text_customization']['public_thank_you_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thank you message'),
      '#description' => $this->t('Shown after a successful payment. Leave empty to use the default text.'),
      '#default_value' => (string) ($config->get('public_thank_you_message') ?? ''),
      '#placeholder' => $this->publicTextProvider->getDefaultThankYouMessage(),
      '#maxlength' => 255,
    ];

    $form['public_text_customization']['public_payment_button_cta'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Payment button CTA'),
      '#description' => $this->t('Shown on the main payment button in the modal. Leave empty to use the default text.'),
      '#default_value' => (string) ($config->get('public_payment_button_cta') ?? ''),
      '#placeholder' => $this->publicTextProvider->getDefaultPaymentButtonCta(),
      '#maxlength' => 255,
    ];

    $form = parent::buildForm($form, $form_state);

    // Keep submit action inside collapsible section.
    if (isset($form['actions']['submit'])) {
      $form['public_text_customization']['actions'] = $form['actions'];
      $form['public_text_customization']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset to defaults'),
        '#submit' => ['::resetToDefaultsSubmitForm'],
        '#validate' => [],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'onclick' => "return confirm('Are you sure you want to reset these values to defaults?');",
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
    $form_state->setValue('public_call_to_action', trim((string) $form_state->getValue('public_call_to_action')));
    $form_state->setValue('public_thank_you_message', trim((string) $form_state->getValue('public_thank_you_message')));
    $form_state->setValue('public_payment_button_cta', trim((string) $form_state->getValue('public_payment_button_cta')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->set('public_call_to_action', (string) $form_state->getValue('public_call_to_action'))
      ->set('public_thank_you_message', (string) $form_state->getValue('public_thank_you_message'))
      ->set('public_payment_button_cta', (string) $form_state->getValue('public_payment_button_cta'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Resets public text customization values back to defaults.
   */
  public function resetToDefaultsSubmitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory
      ->getEditable('taler_payments.settings')
      ->clear('public_call_to_action')
      ->clear('public_thank_you_message')
      ->clear('public_payment_button_cta')
      ->save();

    $this->messenger()->addStatus($this->t('Public text customization has been reset to defaults.'));
  }

}
