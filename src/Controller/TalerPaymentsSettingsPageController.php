<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Builds the Taler Payments admin settings page with separate forms.
 */
final class TalerPaymentsSettingsPageController extends ControllerBase {

  /**
   * Returns the settings page render array.
   */
  public function __invoke(): array {
    return [
      'base_url_form' => $this->formBuilder()->getForm('\Drupal\taler_payments\Form\TalerPaymentsSettingsForm'),
      'username_password_form' => $this->formBuilder()->getForm('\Drupal\taler_payments\Form\TalerPaymentsUsernamePasswordSettingsForm'),
      'access_token_form' => $this->formBuilder()->getForm('\Drupal\taler_payments\Form\TalerPaymentsAccessTokenSettingsForm'),
      'public_text_customization_form' => $this->formBuilder()->getForm('\Drupal\taler_payments\Form\TalerPaymentsPublicTextSettingsForm'),
    ];
  }

}
