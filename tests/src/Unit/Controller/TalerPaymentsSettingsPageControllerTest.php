<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\taler_payments\Controller\TalerPaymentsSettingsPageController;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Taler Payments settings page controller.
 *
 * @coversDefaultClass \Drupal\taler_payments\Controller\TalerPaymentsSettingsPageController
 * @group taler_payments
 */
final class TalerPaymentsSettingsPageControllerTest extends TestCase {

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * @covers ::__invoke
   */
  public function testInvokeReturnsSettingsPageRenderArray(): void {
    $expected_classes = [
      '\Drupal\taler_payments\Form\TalerPaymentsSettingsForm',
      '\Drupal\taler_payments\Form\TalerPaymentsUsernamePasswordSettingsForm',
      '\Drupal\taler_payments\Form\TalerPaymentsAccessTokenSettingsForm',
      '\Drupal\taler_payments\Form\TalerPaymentsPublicTextSettingsForm',
    ];

    $form_builder = $this->createMock(FormBuilderInterface::class);
    $received_classes = [];
    $form_builder->expects($this->exactly(4))
      ->method('getForm')
      ->willReturnCallback(function (string $form_class) use (&$received_classes): array {
        $received_classes[] = $form_class;
        return ['#stub' => $form_class];
      });

    $container = new ContainerBuilder();
    $container->set('form_builder', $form_builder);
    \Drupal::setContainer($container);

    $controller = new TalerPaymentsSettingsPageController();
    $result = $controller->__invoke();

    $this->assertSame($expected_classes, $received_classes);
    $this->assertSame([
      'base_url_form' => ['#stub' => $expected_classes[0]],
      'username_password_form' => ['#stub' => $expected_classes[1]],
      'access_token_form' => ['#stub' => $expected_classes[2]],
      'public_text_customization_form' => ['#stub' => $expected_classes[3]],
    ], $result);
  }

}
