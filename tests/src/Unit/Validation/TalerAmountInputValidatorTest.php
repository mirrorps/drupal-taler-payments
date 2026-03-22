<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Validation;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taler_payments\Validation\TalerAmountInputValidator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taler_payments\Validation\TalerAmountInputValidator
 *
 * @group taler_payments
 */
final class TalerAmountInputValidatorTest extends TestCase {

  private function createValidator(): TalerAmountInputValidator {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn (string $string, array $args = [], array $options = []): TranslatableMarkup => new TranslatableMarkup($string, $args, $options, $translation));

    return new TalerAmountInputValidator($translation);
  }

  /**
   * @dataProvider validAmountProvider
   */
  public function test_validate_accepts_valid_amounts(string $amount): void {
    $validator = $this->createValidator();
    $this->assertNull($validator->validate($amount));
  }

  /**
   * @return array<string, array{0: string}>
   */
  public static function validAmountProvider(): array {
    return [
      'eur cents' => ['EUR:11.12'],
      'kudos fractional' => ['KUDOS:0.11'],
      'integer only' => ['EUR:10'],
    ];
  }

  public function test_validate_rejects_empty_string(): void {
    $validator = $this->createValidator();
    $this->assertNotNull($validator->validate(''));
  }

  public function test_validate_rejects_more_than_two_decimal_places(): void {
    $validator = $this->createValidator();
    $this->assertNotNull($validator->validate('EUR:1.123'));
  }

  public function test_validate_rejects_two_letter_currency_for_taler_sdk(): void {
    $validator = $this->createValidator();
    // Passes local regex (2+ letters) but fails Taler\Api\Order\Dto\Amount (3–11 letters).
    $this->assertNotNull($validator->validate('EU:10'));
  }

  public function test_validate_trims_whitespace(): void {
    $validator = $this->createValidator();
    $this->assertNull($validator->validate('  EUR:1.00  '));
  }

  public function test_validate_rejects_whitespace_only(): void {
    $validator = $this->createValidator();
    $this->assertNotNull($validator->validate('   '));
  }

  /**
   * @dataProvider invalidFormatProvider
   */
  public function test_validate_rejects_invalid_local_format(string $amount): void {
    $validator = $this->createValidator();
    $this->assertNotNull($validator->validate($amount));
  }

  /**
   * @return array<string, array{0: string}>
   */
  public static function invalidFormatProvider(): array {
    return [
      'no colon' => ['EUR10'],
      'single letter currency' => ['E:10'],
      'non letter in currency' => ['EU1:10'],
      'leading dot in value' => ['EUR:.5'],
      'trailing dot' => ['EUR:10.'],
      'three fractional digits' => ['USD:0.001'],
    ];
  }

  public function test_validate_dto_failure_returns_translatable_markup(): void {
    $validator = $this->createValidator();
    $result = $validator->validate('EU:10');
    $this->assertNotNull($result);
    $this->assertInstanceOf(TranslatableMarkup::class, $result);
  }

}
