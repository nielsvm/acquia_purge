<?php

namespace Drupal\acquia_purge\Plugin\Purge\TagsHeader;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;
use Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface;
use Drupal\acquia_purge\AcquiaPlatformCdn\BackendFactory;

/**
 * Exports a tags header for the current Platform CDN backend.
 *
 * @PurgeTagsHeader(
 *   id = "acquiapurgecdntagsheader",
 *   header_name = "X-Acquia-Purge-Cdn-Unconfigured",
 *   dependent_purger_plugins = {"acquia_platform_cdn"},
 * )
 */
class AcquiaPlatformCdnTagsHeader extends TagsHeaderBase implements TagsHeaderInterface {

  /**
   * @var string
   */
  protected $backendClass = NULL;

  /**
   * @var \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface
   */
  protected $hostingInfo;

  /**
   * Constructs a AcquiaPlatformCdnTagsHeader object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acquia_purge\AcquiaCloud\HostingInfoInterface $acquia_purge_hostinginfo
   *   Provides technical information accessors for Acquia Cloud.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HostingInfoInterface $acquia_purge_hostinginfo) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hostingInfo = $acquia_purge_hostinginfo;
    $this->backendClass = BackendFactory::getClass($this->hostingInfo);
    if ($this->backendClass) {
      return $this->backendClass::hostingInfo($this->hostingInfo);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('acquia_purge.hostinginfo')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaderName() {
    if ($this->backendClass) {
      return $this->backendClass::tagsHeaderName();
    }
    return $this->getPluginDefinition()['header_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(array $tags) {
    if ($this->backendClass) {
      return $this->backendClass::tagsHeaderValue($tags);
    }
    return 'n/a';
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    if ($this->backendClass) {
      return TRUE;
    }
    return FALSE;
  }

}
