<?php

namespace Drupal\acquia_purge\AcquiaPlatformCdn;

use GuzzleHttp\ClientInterface;
use Drupal\purge\Logger\LoggerChannelPartInterface;
use Drupal\purge\Logger\PurgeLoggerAwareTrait;
use Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface;
use Drupal\acquia_purge\Plugin\Purge\Purger\DebuggerAwareTrait;
use Drupal\acquia_purge\Plugin\Purge\Purger\DebuggerInterface;

/**
 * Provides a Fastly backend for the Platform CDN purger.
 */
abstract class BackendBase implements BackendInterface {
  use PurgeLoggerAwareTrait;
  use DebuggerAwareTrait;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Acquia Platform CDN configuration settings.
   *
   * Associative array with arbitrary settings coming from:
   * \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface::getPlatformCdnConfiguration.
   *
   * @var array
   */
  protected $config;

  /**
   * API to retrieve technical information from Acquia Cloud.
   *
   * @var \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface
   */
  protected $hostingInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $config, HostingInfoInterface $acquia_purge_hostinginfo, LoggerChannelPartInterface $logger, DebuggerInterface $debugger, ClientInterface $http_client) {
    $this->hostingInfo = $acquia_purge_hostinginfo;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->config = $config;
    $this->setDebugger($debugger);
  }

  /**
   * {@inheritdoc}
   */
  public static function getTemporaryRuntimeError() {
    if ($error = \Drupal::cache()->get('acquia_purge_cdn_runtime_error')) {
      return $error->data;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public static function hostingInfo(HostingInfoInterface $set = NULL) {
    static $hostinginfo;
    if (is_null($hostinginfo) && (!is_null($set))) {
      $hostinginfo = $set;
    }
    elseif (is_null($hostinginfo)) {
      throw new \RuntimeException("BackendBase::hostingInfo can't deliver requested instance.");
    }
    return $hostinginfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function setTemporaryRuntimeError($message, $timeout = 300) {
    \Drupal::cache()->set(
      'acquia_purge_cdn_runtime_error',
      $message,
      time() + $timeout
    );
  }

}
