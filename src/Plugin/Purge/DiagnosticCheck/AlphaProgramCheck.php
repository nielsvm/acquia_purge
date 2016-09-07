<?php

namespace Drupal\acquia_purge\Plugin\Purge\DiagnosticCheck;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;

/**
 * Special check for the AP8 Alpha Program (removed later).
 *
 * @PurgeDiagnosticCheck(
 *   id = "ap8_alpha_program",
 *   title = @Translation("Acquia Cloud"),
 *   description = @Translation(""),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {"acquia_purge"}
 * )
 */
class AlphaProgramCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructs a AlphaProgrammeCheck object.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   Drupal site settings object.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(Settings $settings, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('settings'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    if (!is_null($secret = $this->settings->get('acquia_purge_alpha'))) {
      if (hash('sha256', $secret) == 'f781817a5ce0b9cb286c74cb936c874a91ecde5733bb71c0d16d363e1c80be2f') {
        $this->value = $this->t("Participating in the AP alpha programme!");
        return SELF::SEVERITY_OK;
      }
    }
    $this->recommendation = $this->t("The acquia_purge module isn't ready for prime time yet. If you want join our alpha-testing programme, you will need dedicated load balancers and a access key. Please contact Acquia support!");
    return SELF::SEVERITY_ERROR;
  }

}
