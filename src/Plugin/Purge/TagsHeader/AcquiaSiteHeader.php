<?php

namespace Drupal\acquia_purge\Plugin\Purge\TagsHeader;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;
use Drupal\acquia_purge\HostingInfoInterface;

/**
 * Exports the X-Acquia-Site header.
 *
 * @PurgeTagsHeader(
 *   id = "acquiapurgesiteheader",
 *   header_name = "X-Acquia-Site",
 * )
 */
class AcquiaSiteHeader extends TagsHeaderBase implements TagsHeaderInterface {

  /**
   * @var \Drupal\acquia_purge\HostingInfoInterface
   */
  protected $acquia_purge_hostinginfo;

  /**
   * Constructs a AcquiaSiteHeader object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acquia_purge\HostingInfoInterface $acquia_purge_hostinginfo
   *   Provides technical information accessors for Acquia Cloud.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HostingInfoInterface $acquia_purge_hostinginfo) {
    $this->acquia_purge_hostinginfo = $acquia_purge_hostinginfo;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
  public function getValue(array $tags) {
    return $this->acquia_purge_hostinginfo->getSiteName();
  }

}
