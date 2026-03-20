<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Security;

use Drupal\Core\Site\Settings;

/**
 * Encrypts sensitive credentials before config persistence.
 */
final class TalerCredentialEncryptor {

  /**
   * Indicates whether encryption is available in this environment.
   */
  public function isEncryptionAvailable(): bool {
    return function_exists('openssl_encrypt') && Settings::getHashSalt() !== '';
  }

  /**
   * Encrypts plain text to a transport-safe payload.
   *
   * @throws \RuntimeException
   */
  public function encrypt(string $plainText): string {
    if (!$this->isEncryptionAvailable()) {
      throw new \RuntimeException('Encryption prerequisites are not available.');
    }

    $key = hash('sha256', Settings::getHashSalt(), TRUE);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === FALSE) {
      throw new \RuntimeException('Credential encryption failed.');
    }

    return base64_encode($iv . $ciphertext);
  }

  /**
   * Decrypts a previously encrypted credential payload.
   *
   * @throws \RuntimeException
   */
  public function decrypt(string $encryptedPayload): string {
    if (!$this->isEncryptionAvailable()) {
      throw new \RuntimeException('Encryption prerequisites are not available.');
    }

    $decoded = base64_decode($encryptedPayload, TRUE);
    if ($decoded === FALSE || strlen($decoded) <= 16) {
      throw new \RuntimeException('Encrypted credential payload is invalid.');
    }

    $iv = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);
    $key = hash('sha256', Settings::getHashSalt(), TRUE);

    $plainText = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plainText === FALSE) {
      throw new \RuntimeException('Credential decryption failed.');
    }

    return $plainText;
  }

}
