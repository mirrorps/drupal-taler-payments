<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Checkout\TalerCheckoutManagerInterface;
use Drupal\taler_payments\Form\TalerCheckoutStartForm;
use Drupal\taler_payments\Validation\TalerAmountInputValidatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\taler_payments\Form\TalerCheckoutStartForm
 * @group taler_payments
 */
final class TalerCheckoutStartFormTest extends TestCase {

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  private function createTranslationStub(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn (string $string, array $args = [], array $options = []): TranslatableMarkup => new TranslatableMarkup($string, $args, $options, $translation));

    return $translation;
  }

  private function createForm(
    ?TalerCheckoutManagerInterface $checkout_manager = NULL,
    ?TalerAmountInputValidatorInterface $amount_validator = NULL,
  ): TalerCheckoutStartForm {
    $form = new TalerCheckoutStartForm(
      $checkout_manager ?? $this->createMock(TalerCheckoutManagerInterface::class),
      $amount_validator ?? $this->createMock(TalerAmountInputValidatorInterface::class),
    );
    $form->setStringTranslation($this->createTranslationStub());
    return $form;
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormCreatesSubmitButtonAndLibrary(): void {
    $form = $this->createForm();

    $built_form = $form->buildForm([], new FormState(), [
      'button_text' => 'Pay now',
      'title' => 'Donation',
      'amount' => ' EUR:1.00 ',
      'summary' => ' Summary ',
    ]);

    $this->assertSame(['taler-payment-button-wrapper'], $built_form['#attributes']['class']);
    $this->assertSame('submit', $built_form['actions']['submit']['#type']);
    $this->assertSame('Pay now', $built_form['actions']['submit']['#value']);
    $this->assertSame(['taler-payment-button'], $built_form['actions']['submit']['#attributes']['class']);
    $this->assertSame('true', $built_form['actions']['submit']['#attributes']['data-disable-once']);
    $this->assertSame('Donation', $built_form['#taler_checkout_configuration']['title']);
    $this->assertSame('EUR:1.00', $built_form['#taler_checkout_configuration']['amount']);
    $this->assertSame('Summary', $built_form['#taler_checkout_configuration']['summary']);
    $this->assertSame(['taler_payments/payment_button'], $built_form['#attached']['library']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsInvalidServerSideAmountConfiguration(): void {
    $translation = $this->createTranslationStub();
    $error = new TranslatableMarkup('Invalid amount.', [], [], $translation);
    $amount_validator = $this->createMock(TalerAmountInputValidatorInterface::class);
    $amount_validator->expects($this->once())
      ->method('validate')
      ->with('bad')
      ->willReturn($error);

    $form = $this->createForm(NULL, $amount_validator);
    $built_form = $form->buildForm([], new FormState(), [
      'button_text' => 'Pay',
      'title' => 'Donation',
      'amount' => 'bad',
      'summary' => '',
    ]);

    $form_state = new FormState();
    $form->validateForm($built_form, $form_state);

    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormStartsCheckoutAndRedirects(): void {
    $checkout_manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $checkout_manager->expects($this->once())
      ->method('beginCheckout')
      ->with('Donation', 'EUR:1.00', 'Summary')
      ->willReturn(['order_id' => 'order-123']);

    $amount_validator = $this->createMock(TalerAmountInputValidatorInterface::class);
    $form = $this->createForm($checkout_manager, $amount_validator);
    $built_form = $form->buildForm([], new FormState(), [
      'button_text' => 'Pay',
      'title' => 'Donation',
      'amount' => 'EUR:1.00',
      'summary' => 'Summary',
    ]);

    $form_state = new FormState();
    $form->submitForm($built_form, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertInstanceOf(Url::class, $redirect);
    $this->assertSame('taler_payments.checkout_page', $redirect->getRouteName());
    $this->assertSame(['order_id' => 'order-123'], $redirect->getRouteParameters());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormLogsGenericFailureAndShowsUserMessage(): void {
    $checkout_manager = $this->createMock(TalerCheckoutManagerInterface::class);
    $checkout_manager->expects($this->once())
      ->method('beginCheckout')
      ->willThrowException(new \RuntimeException('backend exploded'));

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Could not start Taler checkout. Error type: @type',
        ['@type' => 'RuntimeException'],
      );

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('taler_payments')
      ->willReturn($logger);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addError')
      ->with(
        $this->callback(static function (mixed $message): bool {
          return $message instanceof TranslatableMarkup
            && $message->getUntranslatedString() === 'Could not start the Taler checkout. Please try again later.';
        }),
        FALSE,
      );

    $container = new ContainerBuilder();
    $container->set('logger.factory', $logger_factory);
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);

    $form = $this->createForm($checkout_manager, $this->createMock(TalerAmountInputValidatorInterface::class));
    $built_form = $form->buildForm([], new FormState(), [
      'button_text' => 'Pay',
      'title' => 'Donation',
      'amount' => 'EUR:1.00',
      'summary' => 'Summary',
    ]);

    $form->submitForm($built_form, new FormState());

    $this->addToAssertionCount(1);
  }

}
