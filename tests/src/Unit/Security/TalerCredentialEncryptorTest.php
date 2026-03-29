<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Security;

use Drupal\Core\Site\Settings;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taler_payments\Security\TalerCredentialEncryptor
 * @group taler_payments
 */
final class TalerCredentialEncryptorTest extends TestCase {

  /**
   * @covers ::isEncryptionAvailable
   */
  public function testIsEncryptionAvailableReturnsFalseWhenSettingsAreMissing(): void {
    $encryptor = new TalerCredentialEncryptor();
    $this->assertFalse($encryptor->isEncryptionAvailable());
  }

  /**
   * @covers ::encrypt
   * @covers ::decrypt
   */
  public function testEncryptDecryptRoundTrip(): void {
    if (!function_exists('openssl_encrypt')) {
      $this->markTestSkipped('OpenSSL is not available.');
    }

    new Settings([
      'hash_salt' => 'unit-test-hash-salt',
    ]);

    $encryptor = new TalerCredentialEncryptor();
    $this->assertTrue($encryptor->isEncryptionAvailable());

    $payload = $encryptor->encrypt('Bearer secret-token:sandbox');
    $this->assertStringStartsWith('v2:', $payload);
    $this->assertNotSame('', $payload);
    $this->assertSame('Bearer secret-token:sandbox', $encryptor->decrypt($payload));
  }

  /**
   * @covers ::encrypt
   */
  public function testEncryptThrowsWhenEncryptionIsUnavailable(): void {
    new Settings([]);

    $encryptor = new TalerCredentialEncryptor();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Encryption prerequisites are not available.');
    $encryptor->encrypt('secret');
  }

}
