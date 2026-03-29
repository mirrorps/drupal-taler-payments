<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Security;

use Drupal\Core\Site\Settings;

/**
 * Encrypts sensitive credentials before config persistence.
 */
class TalerCredentialEncryptor {

  private const CURRENT_PREFIX = 'v2:';
  private const GCM_IV_BYTES = 12;
  private const GCM_TAG_BYTES = 16;

  /**
   * Indicates whether encryption is available in this environment.
   */
  public function isEncryptionAvailable(): bool {
    if (!function_exists('openssl_encrypt')) {
      return FALSE;
    }

    try {
      return Settings::getHashSalt() !== '';
    }
    catch (\Throwable) {
      return FALSE;
    }
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
    $iv = random_bytes(self::GCM_IV_BYTES);
    $tag = '';
    $ciphertext = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::GCM_TAG_BYTES);

    if ($ciphertext === FALSE) {
      throw new \RuntimeException('Credential encryption failed.');
    }

    return self::CURRENT_PREFIX . base64_encode($iv . $tag . $ciphertext);
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

    if (!str_starts_with($encryptedPayload, self::CURRENT_PREFIX)) {
      throw new \RuntimeException('Encrypted credential payload is invalid.');
    }

    return $this->decryptCurrentPayload(substr($encryptedPayload, strlen(self::CURRENT_PREFIX)));
  }

  /**
   * Decrypts the authenticated payload format currently written by the module.
   *
   * @throws \RuntimeException
   */
  private function decryptCurrentPayload(string $payload): string {
    $decoded = base64_decode($payload, TRUE);
    $minimum_length = self::GCM_IV_BYTES + self::GCM_TAG_BYTES + 1;

    if ($decoded === FALSE || strlen($decoded) < $minimum_length) {
      throw new \RuntimeException('Encrypted credential payload is invalid.');
    }

    $iv = substr($decoded, 0, self::GCM_IV_BYTES);
    $tag = substr($decoded, self::GCM_IV_BYTES, self::GCM_TAG_BYTES);
    $ciphertext = substr($decoded, self::GCM_IV_BYTES + self::GCM_TAG_BYTES);
    $key = hash('sha256', Settings::getHashSalt(), TRUE);

    $plainText = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plainText === FALSE) {
      throw new \RuntimeException('Credential decryption failed.');
    }

    return $plainText;
  }

}
