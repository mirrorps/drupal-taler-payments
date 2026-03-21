<?php

declare(strict_types=1);

namespace Drupal\Tests\taler_payments\Unit\Service;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\taler_payments\Security\TalerCredentialEncryptor;
use Drupal\taler_payments\Service\TalerClientManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\taler_payments\Service\TalerClientManager
 * @group taler_payments
 */
final class TalerClientManagerTest extends TestCase {

  /**
   * @covers ::validateAccessTokenCredentials
   */
  public function testValidateAccessTokenCredentialsRejectsMissingBaseUrl(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->with('taler_base_url')
           ->willReturn('');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler base URL is not configured.');
    $manager->validateAccessTokenCredentials('Bearer token');
  }

  /**
   * @covers ::validateAccessTokenCredentials
   */
  public function testValidateAccessTokenCredentialsRejectsMissingToken(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->with('taler_base_url')
           ->willReturn('https://backend.example/instances/default');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler access token is not configured.');
    $manager->validateAccessTokenCredentials('   ');
  }

  /**
   * @covers ::validateUsernamePasswordCredentials
   */
  public function testValidateUsernamePasswordCredentialsRejectsMissingBaseUrl(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->with('taler_base_url')
           ->willReturn('');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler base URL is not configured.');
    $manager->validateUsernamePasswordCredentials('default', 'user', 'pass');
  }

  /**
   * @covers ::validateUsernamePasswordCredentials
   */
  public function testValidateUsernamePasswordCredentialsRejectsMissingValues(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->with('taler_base_url')
           ->willReturn('https://backend.example/instances/default');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler username/password credentials are not fully configured.');
    $manager->validateUsernamePasswordCredentials('  ', 'user', 'pass');
  }

  /**
   * @covers ::getClient
   */
  public function testGetClientRejectsMissingBaseUrl(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->willReturnMap([
             ['taler_base_url', ''],
             ['instance_id', ''],
             ['username', ''],
             ['password', ''],
             ['access_token', ''],
           ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler base URL is not configured.');
    $manager->getClient();
  }

  /**
   * @covers ::getClient
   */
  public function testGetClientRejectsUndecryptableAccessToken(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->willReturnMap([
             ['taler_base_url', 'https://backend.example/instances/default'],
             ['instance_id', ''],
             ['username', ''],
             ['password', ''],
             ['access_token', 'encrypted-token'],
           ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $encryptor->expects($this->once())
              ->method('decrypt')
              ->with('encrypted-token')
              ->willReturn('');

    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler access token could not be decrypted.');
    $manager->getClient();
  }

  /**
   * @covers ::getClient
   */
  public function testGetClientRejectsMissingUsernamePasswordConfig(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->willReturnMap([
             ['taler_base_url', 'https://backend.example/instances/default'],
             ['instance_id', ''],
             ['username', ''],
             ['password', ''],
             ['access_token', ''],
           ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler username/password credentials are not fully configured.');
    $manager->getClient();
  }

  /**
   * @covers ::getClient
   */
  public function testGetClientRejectsUndecryptablePassword(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
           ->willReturnMap([
             ['taler_base_url', 'https://backend.example/instances/default'],
             ['instance_id', 'default'],
             ['username', 'user'],
             ['password', 'encrypted-pass'],
             ['access_token', ''],
           ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
                   ->with('taler_payments.settings')
                   ->willReturn($config);

    $encryptor = $this->createMock(TalerCredentialEncryptor::class);
    $encryptor->expects($this->once())
              ->method('decrypt')
              ->with('encrypted-pass')
              ->willReturn('');

    $manager = new TalerClientManager($config_factory, $encryptor);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Taler password could not be decrypted.');
    $manager->getClient();
  }

}
