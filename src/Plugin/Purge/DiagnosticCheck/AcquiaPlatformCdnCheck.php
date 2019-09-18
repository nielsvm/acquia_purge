<?php

namespace Drupal\acquia_purge\Plugin\Purge\DiagnosticCheck;

use Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface;
use Drupal\acquia_purge\AcquiaPlatformCdn\BackendFactory;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Acquia Purge CDN.
 *
 * @PurgeDiagnosticCheck(
 *   id = "acquia_purge_platformcdn_check",
 *   title = @Translation("Acquia Platform CDN"),
 *   description = @Translation("Validates the Acquia Platform CDN configuration."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"acquia_platform_cdn"}
 * )
 */
class AcquiaPlatformCdnCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * API to retrieve technical information from Acquia Cloud.
   *
   * @var \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface
   */
  protected $hostingInfo;

  /**
   * Constructs a AcquiaPlatformCdnCheck object.
   *
   * @param \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface $acquia_purge_hostinginfo
   *   Technical information accessors for the Acquia Cloud environment.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(HostingInfoInterface $acquia_purge_hostinginfo, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hostingInfo = $acquia_purge_hostinginfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('acquia_purge.hostinginfo'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    // Verify if we're able to fetch the Platform CDN configuration array.
    if (!$backend_config = BackendFactory::getConfig($this->hostingInfo)) {
      $this->recommendation = $this->t("Platform CDN isn't yet configured, please contact Acquia Support to do this for you.");
      return self::SEVERITY_ERROR;
    }
    // Lookup a backend class for the Platform CDN vendor.
    if (!($backend_class = BackendFactory::getClassFromConfig($backend_config))) {
      $this->recommendation = $this->t("Please upgrade Acquia Purge to a newer version.");
      return self::SEVERITY_ERROR;
    }
    // Ask the backend plugin to validate the Platform CDN configuration array.
    if (!$backend_class::validateConfiguration($backend_config)) {
      $this->recommendation = $this->t("Configuration malformed, please contact Acquia Support.");
      return self::SEVERITY_ERROR;
    }
    // Check for temporary runtime errors and suspend operations if present.
    if ($error = $backend_class::getTemporaryRuntimeError()) {
      $this->recommendation = $error;
      return self::SEVERITY_ERROR;
    }
    $this->recommendation = $this->t("Acquia Platform CDN is enabled.");
    return self::SEVERITY_OK;
  }

}
