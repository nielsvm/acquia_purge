<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface;

/**
 * Acquia Platform CDN (beta).
 *
 * @PurgePurger(
 *   id = "acquia_platform_cdn",
 *   label = @Translation("Acquia Platform CDN (beta)"),
 *   configform = "",
 *   cooldown_time = 0.0,
 *   description = @Translation("Invalidate content from Acquia Platform CDN."),
 *   multi_instance = FALSE,
 *   types = {"url", "tag", "everything"},
 * )
 */
class AcquiaPlatformCdnPurger extends PurgerBase implements DebuggerAwareInterface, PurgerInterface {
  use DebuggerAwareTrait;

  /**
   * The Acquia Platform CDN backend.
   *
   * @var \Drupal\acquia_purge\AcquiaPlatformCdn\BackendInterface
   */
  protected $backend = NULL;

  /**
   * API to retrieve technical information from Acquia Cloud.
   *
   * @var \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface
   */
  protected $hostingInfo;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a AcquiaCdnPurger object.
   *
   * @param \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface $acquia_purge_hostinginfo
   *   Technical information accessors for the Acquia Cloud environment.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(HostingInfoInterface $acquia_purge_hostinginfo, ClientInterface $http_client, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hostingInfo = $acquia_purge_hostinginfo;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('acquia_purge.hostinginfo'),
      $container->get('http_client'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return TRUE;
  }

  /**
   * Lazy load the underlying backend based on HostingInfo CDN configuration.
   *
   * @warning
   *   Don't call this from the constructor!
   */
  protected function initializeBackend() {
    if (!is_null($this->backend)) {
      return;
    }
    $this->debugger()->callerAdd(__METHOD__);

    // Attempt to load the backend or halt code execution.
    $this->backend = BackendFactory::get(
      $this->hostingInfo,
      $this->logger(),
      $this->debugger(),
      $this->httpClient
    );
    if (!$this->backend) {
      throw new \RuntimeException("AcquiaPlatformCdnPurger has no backend!");
    }

    // Instantiate the backend and inject the logger.
    $this->debugger()->callerAdd($this->backend);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {

    // Since we implemented ::routeTypeToMethod(), this Latin preciousness
    // shouldn't ever occur and when it does, will be easily recognized.
    throw new \Exception("Malum consilium quod mutari non potest!");
  }

  /**
   * Invalidate a set of tag invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateTags(array $invalidations) {
    $this->initializeBackend();
    $this->debugger()->callerAdd(__METHOD__);
    $this->backend->invalidateTags($invalidations);
    $this->debugger()->callerRemove(__METHOD__);
  }

  /**
   * Invalidate a set of URL invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateUrls(array $invalidations) {
    $this->initializeBackend();
    $this->debugger()->callerAdd(__METHOD__);
    $this->backend->invalidateUrls($invalidations);
    $this->debugger()->callerRemove(__METHOD__);
  }

  /**
   * Invalidate the entire CDN.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateEverything(array $invalidations) {
    $this->initializeBackend();
    $this->debugger()->callerAdd(__METHOD__);
    $this->backend->invalidateEverything($invalidations);
    $this->debugger()->callerRemove(__METHOD__);
  }

  /**
   * {@inheritdoc}
   */
  public function routeTypeToMethod($type) {
    $methods = [
      'tag'         => 'invalidateTags',
      'url'         => 'invalidateUrls',
      'everything'  => 'invalidateEverything',
    ];
    return isset($methods[$type]) ? $methods[$type] : 'invalidate';
  }

}
