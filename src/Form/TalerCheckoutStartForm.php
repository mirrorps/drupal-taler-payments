<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taler_payments\Checkout\TalerCheckoutManagerInterface;
use Drupal\taler_payments\Validation\TalerAmountInputValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Starts checkout from a CSRF-protected Drupal form submission.
 */
final class TalerCheckoutStartForm extends FormBase {

  public function __construct(
    private readonly TalerCheckoutManagerInterface $checkoutManager,
    private readonly TalerAmountInputValidatorInterface $amountInputValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $checkout_manager = $container->get('taler_payments.checkout_manager');
    if (!$checkout_manager instanceof TalerCheckoutManagerInterface) {
      throw new \InvalidArgumentException('Service taler_payments.checkout_manager must implement TalerCheckoutManagerInterface.');
    }

    $amount_validator = $container->get('taler_payments.amount_input_validator');
    if (!$amount_validator instanceof TalerAmountInputValidatorInterface) {
      throw new \InvalidArgumentException('Service taler_payments.amount_input_validator must implement TalerAmountInputValidatorInterface.');
    }

    return new static($checkout_manager, $amount_validator);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'taler_payments_checkout_start_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, string> $configuration
   *   Server-side button configuration.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $configuration = []): array {
    $configuration = $this->normalizeConfiguration($configuration);

    $form['#attributes']['class'][] = 'taler-payment-button-wrapper';
    $form['#taler_checkout_configuration'] = $configuration;
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $configuration['button_text'],
      '#attributes' => [
        'class' => ['taler-payment-button'],
        'data-disable-once' => 'true',
      ],
    ];
    $form['#attached']['library'][] = 'taler_payments/payment_button';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $configuration = $this->getConfigurationFromForm($form);
    $amount_error = $this->amountInputValidator->validate($configuration['amount']);

    if ($amount_error !== NULL) {
      $form_state->setErrorByName('actions', $this->t('Checkout configuration is invalid: @detail', [
        '@detail' => (string) $amount_error,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $configuration = $this->getConfigurationFromForm($form);

    try {
      $intent = $this->checkoutManager->beginCheckout(
        $configuration['title'],
        $configuration['amount'],
        $configuration['summary'],
      );
    }
    catch (\Throwable $exception) {
      \Drupal::logger('taler_payments')->error(
        'Could not start Taler checkout. Error type: @type',
        ['@type' => get_debug_type($exception)],
      );
      $this->messenger()->addError($this->t('Could not start the Taler checkout. Please try again later.'));
      return;
    }

    $form_state->setRedirect('taler_payments.checkout_page', [
      'order_id' => (string) $intent['order_id'],
    ]);
  }

  /**
   * @param array<string, mixed> $form
   *
   * @return array{button_text: string, title: string, amount: string, summary: string}
   */
  private function getConfigurationFromForm(array $form): array {
    $configuration = $form['#taler_checkout_configuration'] ?? [];
    return $this->normalizeConfiguration(is_array($configuration) ? $configuration : []);
  }

  /**
   * @param array<string, mixed> $configuration
   *
   * @return array{button_text: string, title: string, amount: string, summary: string}
   */
  private function normalizeConfiguration(array $configuration): array {
    return [
      'button_text' => (string) ($configuration['button_text'] ?? ''),
      'title' => trim((string) ($configuration['title'] ?? '')),
      'amount' => trim((string) ($configuration['amount'] ?? '')),
      'summary' => trim((string) ($configuration['summary'] ?? '')),
    ];
  }

}
