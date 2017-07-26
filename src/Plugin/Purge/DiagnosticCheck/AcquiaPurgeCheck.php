<?php

namespace Drupal\acquia_purge\Plugin\Purge\DiagnosticCheck;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\acquia_purge\HostingInfoInterface;

/**
 * Acquia Purge.
 *
 * @PurgeDiagnosticCheck(
 *   id = "acquia_purge",
 *   title = @Translation("Acquia Purge"),
 *   description = @Translation("Reports the status of the Acquia Purge module."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {}
 * )
 */
class AcquiaPurgeCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * @var \Drupal\acquia_purge\HostingInfoInterface
   */
  protected $acquiaPurgeHostinginfo;

  /**
   * @var \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface
   */
  protected $purgePurgers;

  /**
   * Constructs a AcquiaCloudCheck object.
   *
   * @param \Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface $purge_purgers
   *   The purge purgers service.
   * @param \Drupal\acquia_purge\HostingInfoInterface $acquia_purge_hostinginfo
   *   Technical information accessors for the Acquia Cloud environment.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(PurgersServiceInterface $purge_purgers, HostingInfoInterface $acquia_purge_hostinginfo, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->acquiaPurgeHostingInfo = $acquia_purge_hostinginfo;
    $this->purgePurgers = $purge_purgers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('purge.purgers'),
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
    if (!$this->acquiaPurgeHostingInfo->isThisAcquiaCloud()) {
      $this->recommendation = $this->t("Acquia Purge only works on your Acquia Cloud environment and doesn't work outside of it.");
      return SELF::SEVERITY_ERROR;
    }

    // Issue a warning when the user forgot to add the AcquiaCloudPurger.
    if (!in_array('acquia_purge', $this->purgePurgers->getPluginsEnabled())) {
      $this->recommendation = $this->t("The 'Acquia Cloud' purger is not installed!");
      return SELF::SEVERITY_WARNING;
    }

    // Under normal operating conditions, we'll report site info and version.
    $this->value = $this->t(
      "@site_group.@site_env (@version)",
      [
        '@site_group' => $this->acquiaPurgeHostingInfo->getSiteGroup(),
        '@site_env' => $this->acquiaPurgeHostingInfo->getSiteEnvironment(),
        '@version' => $version,
      ]
    );
    $this->recommendation = '';
    return SELF::SEVERITY_OK;
  }

}
