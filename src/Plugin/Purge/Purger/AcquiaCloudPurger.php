<?php

/**
 * @file
 * Contains \Drupal\acquia_purge\Plugin\Purge\Purger\HttpPurger.
 */

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\acquia_purge\HostingInfoInterface;

/**
 * Acquia Cloud.
 *
 * @PurgePurger(
 *   id = "acquia_purge",
 *   label = @Translation("Acquia Cloud"),
 *   configform = "",
 *   cooldown_time = 0.1,
 *   description = @Translation("Invalidates Varnish powered load balancers on your Acquia Cloud site."),
 *   multi_instance = FALSE,
 *   types = {"url"},
 * )
 */
class AcquiaCloudPurger extends PurgerBase implements PurgerInterface {

  /**
   * @var \Drupal\acquia_purge\HostingInfoInterface
   */
  protected $acquiaPurgeHostinginfo;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Constructs a AcquiaCloudPurger object.
   *
   * @param \Drupal\acquia_purge\HostingInfoInterface $acquia_purge_hostinginfo
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
  function __construct(HostingInfoInterface $acquia_purge_hostinginfo, ClientInterface $http_client, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->acquiaPurgeHostingInfo = $acquia_purge_hostinginfo;
    $this->client = $http_client;
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

  // /**
  //  * {@inheritdoc}
  //  */
  // public function getCooldownTime() {
  //   die(__CLASS__.'::'.__METHOD__);
  // }

  // /**
  //  * {@inheritdoc}
  //  */
  // public function getIdealConditionsLimit() {
  //   die(__CLASS__.'::'.__METHOD__);
  // }
  //
  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {
    return 4.0;
  }

  /**
   * Retrieve the URI to connect to.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string
   *   URL string representation.
   */
  protected function getUri($token_data) {
    die(__CLASS__.'::'.__METHOD__);
    // return sprintf(
    //   '%s://%s%s',
    //   $this->settings->scheme,
    //   $this->settings->hostname,
    //   $this->token->replace($this->settings->path, $token_data)
    // );
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    die(__CLASS__.'::'.__METHOD__);
    // $logger = \Drupal::logger('purge_purger_http');
    //
    // // Iterate every single object and fire a request per object.
    // foreach ($invalidations as $invalidation) {
    //   $token_data = ['invalidation' => $invalidation];
    //   $uri = $this->getUri($token_data);
    //   $opt = $this->getOptions($token_data);
    //
    //   try {
    //     $this->client->request($this->settings->request_method, $uri, $opt);
    //     $invalidation->setState(InvalidationInterface::SUCCEEDED);
    //   }
    //   catch (\Exception $e) {
    //     $invalidation->setState(InvalidationInterface::FAILED);
    //     $headers = $opt['headers'];
    //     unset($opt['headers']);
    //     $logger->emergency(
    //       "%exception thrown by %id, invalidation marked as failed. URI: %uri# METHOD: %request_method# HEADERS: %headers#mOPT: %opt#MSG: %exceptionmsg#",
    //       [
    //         '%exception' => get_class($e),
    //         '%exceptionmsg' => $e->getMessage(),
    //         '%request_method' => $this->settings->request_method,
    //         '%opt' => $this->exportDebuggingSymbols($opt),
    //         '%headers' => $this->exportDebuggingSymbols($headers),
    //         '%uri' => $uri,
    //         '%id' => $this->getid()
    //       ]
    //     );
    //   }
    // }
  }

}
