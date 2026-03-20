<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use Taler\Factory\Factory;
use Taler\Taler;

/**
 * Builds configured Taler SDK clients for this Drupal module.
 */
final class TalerClientManager {

  /**
   * Cached SDK client for the current request lifecycle.
   */
  private ?Taler $client = NULL;

  /**
   * Constructs the manager.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TalerCredentialEncryptor $credentialEncryptor,
  ) {}

  /**
   * Returns the configured Taler SDK client.
   *
   * @throws \RuntimeException
   */
  public function getClient(): Taler {
    if ($this->client !== NULL) {
      return $this->client;
    }

    $config = $this->configFactory->get('taler_payments.settings');
    $base_url = trim((string) $config->get('taler_base_url'));
    $instance_id = trim((string) $config->get('instance_id'));
    $username = trim((string) $config->get('username'));
    $encrypted_password = (string) $config->get('password');
    $encrypted_access_token = (string) $config->get('access_token');

    if ($base_url === '') {
      throw new \RuntimeException('Taler base URL is not configured.');
    }

    if ($encrypted_access_token !== '') {
      $access_token = $this->credentialEncryptor->decrypt($encrypted_access_token);
      if ($access_token === '') {
        throw new \RuntimeException('Taler access token could not be decrypted.');
      }

      $this->client = Factory::create([
        'base_url' => $base_url,
        'token' => $access_token,
      ]);

      return $this->client;
    }

    if ($instance_id === '' || $username === '' || $encrypted_password === '') {
      throw new \RuntimeException('Taler username/password credentials are not fully configured.');
    }

    $password = $this->credentialEncryptor->decrypt($encrypted_password);
    if ($password === '') {
      throw new \RuntimeException('Taler password could not be decrypted.');
    }

    $this->client = Factory::create([
      'base_url' => $base_url,
      'username' => $username,
      'password' => $password,
      'instance' => $instance_id,
    ]);

    return $this->client;
  }

  /**
   * Verifies submitted credentials by calling the Taler config endpoint.
   *
   * @throws \RuntimeException
   */
  public function validateUsernamePasswordCredentials(
    string $instance_id,
    string $username,
    string $password,
  ): void {
    $config = $this->configFactory->get('taler_payments.settings');
    $base_url = trim((string) $config->get('taler_base_url'));
    $instance_id = trim($instance_id);
    $username = trim($username);

    if ($base_url === '') {
      throw new \RuntimeException('Taler base URL is not configured.');
    }

    if ($instance_id === '' || $username === '' || $password === '') {
      throw new \RuntimeException('Taler username/password credentials are not fully configured.');
    }

    $client = Factory::create([
      'base_url' => $base_url,
      'username' => $username,
      'password' => $password,
      'instance' => $instance_id,
    ]);
  }

  /**
   * Verifies submitted access token by calling the Taler config endpoint.
   *
   * @throws \RuntimeException
   */
  public function validateAccessTokenCredentials(string $access_token): void {
    $config = $this->configFactory->get('taler_payments.settings');
    $base_url = trim((string) $config->get('taler_base_url'));
    $access_token = trim($access_token);

    if ($base_url === '') {
      throw new \RuntimeException('Taler base URL is not configured.');
    }

    if ($access_token === '') {
      throw new \RuntimeException('Taler access token is not configured.');
    }

    $client = Factory::create([
      'base_url' => $base_url,
      'token' => $access_token,
    ]);

    // enforce an auth-sensitive check before accepting and storing the token.
    $diagnostics = $client->configCheck();
    $auth_ok = ($diagnostics['auth']['ok'] ?? false) === true;

    if (!$auth_ok) {
      throw new \RuntimeException('Taler access token validation failed.');
    }
  }

}
