<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\taler_payments\TalerPaymentButtonBuilderInterface;
use Drupal\taler_payments\Validation\TalerAmountInputValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configurable Taler payment button.
 *
 * @Block(
 *   id = "taler_payment_button",
 *   admin_label = @Translation("Taler payment button"),
 *   category = @Translation("Taler Payments"),
 * )
 */
final class TalerPaymentButtonBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Default label shown on the button when the block is first placed.
   */
  private const DEFAULT_BUTTON_TEXT = 'Pay with Taler';

  /**
   * Default amount (valid Taler amount; currency 3–11 letters per SDK).
   */
  private const DEFAULT_AMOUNT = 'EUR:1.00';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly TalerPaymentButtonBuilderInterface $buttonBuilder,
    private readonly TalerAmountInputValidatorInterface $amountInputValidator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $button_builder = $container->get('taler_payments.payment_button_builder');
    if (!$button_builder instanceof TalerPaymentButtonBuilderInterface) {
      throw new \InvalidArgumentException('Service taler_payments.payment_button_builder must implement TalerPaymentButtonBuilderInterface.');
    }

    $amount_validator = $container->get('taler_payments.amount_input_validator');
    if (!$amount_validator instanceof TalerAmountInputValidatorInterface) {
      throw new \InvalidArgumentException('Service taler_payments.amount_input_validator must implement TalerAmountInputValidatorInterface.');
    }

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $button_builder,
      $amount_validator,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'button_text' => self::DEFAULT_BUTTON_TEXT,
      'summary' => '',
      'amount' => self::DEFAULT_AMOUNT,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#default_value' => $this->configuration['button_text'] ?? self::DEFAULT_BUTTON_TEXT,
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summary'),
      '#default_value' => $this->configuration['summary'] ?? '',
      '#rows' => 4,
      '#description' => $this->t('Summery for the payment.'),
    ];

    $form['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#default_value' => $this->configuration['amount'] ?? self::DEFAULT_AMOUNT,
      '#required' => TRUE,
      '#maxlength' => 128,
      '#description' => $this->t('Format: CURRENCY:VALUE (e.g. KUDOS:0.11, EUR:11.12).<br>Currency: at least two letters; value: integer or up to two decimal places. Must also satisfy GNU Taler amount rules.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $amount = (string) $form_state->getValue('amount');
    $error = $this->amountInputValidator->validate($amount);
    if ($error !== NULL) {
      $form_state->setErrorByName('amount', $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['button_text'] = (string) $form_state->getValue('button_text');
    $this->configuration['summary'] = (string) $form_state->getValue('summary');
    $this->configuration['amount'] = trim((string) $form_state->getValue('amount'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->buttonBuilder->build($this->configuration);
  }

}
