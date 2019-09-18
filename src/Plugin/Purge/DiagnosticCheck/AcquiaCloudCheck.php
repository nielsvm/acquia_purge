<?php

namespace Drupal\acquia_purge\Plugin\Purge\DiagnosticCheck;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface;

/**
 * Acquia Purge.
 *
 * @PurgeDiagnosticCheck(
 *   id = "acquia_purge_cloud_check",
 *   title = @Translation("Acquia Cloud"),
 *   description = @Translation("Validates the Acquia Cloud configuration."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"acquia_purge"}
 * )
 */
class AcquiaCloudCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * API to retrieve technical information from Acquia Cloud.
   *
   * @var \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface
   */
  protected $hostingInfo;

  /**
   * Constructs a AcquiaCloudCheck object.
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
    $version = system_get_info('module', 'acquia_purge')['version'];
    $version = is_null($version) ? '8.x-1.x-dev' : $version;
    $this->value = $version;

    // Block the entire system when this is a third-party platform.
    if (!$this->hostingInfo->isThisAcquiaCloud()) {
      $this->recommendation = $this->t("Acquia Purge only works on your Acquia Cloud environment and doesn't work outside of it.");
      return self::SEVERITY_ERROR;
    }

    // Check the balancer composition for crazy setups.
    $balancers = $this->hostingInfo->getBalancerAddresses();
    $balancerscount = count($balancers);
    $this->value = $balancerscount ? implode(', ', $balancers) : '';
    if (!$balancerscount) {
      $this->value = '';
      $this->recommendation = $this->t("No balancers found, therefore cache invalidation has been disabled. Please contact Acquia Support!");
      return self::SEVERITY_ERROR;
    }
    elseif ($balancerscount < 2) {
      $this->recommendation = $this->t("You have only one load balancer, this means your site cannot be failed over in case of emergency. Please contact Acquia Support!");
      return self::SEVERITY_WARNING;
    }
    elseif ($balancerscount >= 5) {
      $this->recommendation = $this->t("Your site has @n load balancers, which will put severe stress on your system. Please pay attention to your queue, contact Acquia Support and request less but bigger load balancers!", ['@n' => $count]);
      return self::SEVERITY_WARNING;
    }

    // Under normal operating conditions, we'll report site info and version.
    $this->value = $this->t(
      "@site_group.@site_env (@version)",
      [
        '@site_group' => $this->hostingInfo->getSiteGroup(),
        '@site_env' => $this->hostingInfo->getSiteEnvironment(),
        '@version' => $version,
      ]
    );
    $this->recommendation = " ";
    return self::SEVERITY_OK;
  }

}
