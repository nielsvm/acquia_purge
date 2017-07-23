<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\acquia_purge\HostingInfoInterface;
use Drupal\acquia_purge\Hash;

/**
 * Acquia Cloud.
 *
 * @PurgePurger(
 *   id = "acquia_purge_guzzle",
 *   label = @Translation("Acquia Cloud GUZZLE (temporary plugin!)"),
 *   configform = "",
 *   cooldown_time = 0.2,
 *   description = @Translation("Invalidates Varnish powered load balancers on your Acquia Cloud site."),
 *   multi_instance = FALSE,
 *   types = {"url", "wildcardurl", "tag", "everything"},
 * )
 */
class AcquiaCloudGuzzlePurger extends PurgerBase implements PurgerInterface {

  /**
   * Maximum number of requests to send concurrently.
   */
  const CONCURRENCY = 6;

  /**
   * Float describing the number of seconds to wait while trying to connect to
   * a server.
   */
  const CONNECT_TIMEOUT = 0.5;

  /**
   * Float describing the timeout of the request in seconds.
   */
  const TIMEOUT = 2.0;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Supporting variable for ::debug() which is NULL initially, FALSE when there
   * is no RfcLogLevel::DEBUG grant for $this->logger() and [] when debugging.
   *
   * @var null|bool|string[]
   */
  protected $debug = NULL;

  /**
   * @var \Drupal\acquia_purge\HostingInfoInterface
   */
  protected $hostingInfo;

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
  public function __construct(HostingInfoInterface $acquia_purge_hostinginfo, ClientInterface $http_client, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $http_client;
    $this->hostingInfo = $acquia_purge_hostinginfo;
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
   * Log the caller graph using $this->logger()->debug() messages.
   *
   * @param string $caller
   *   Name of the PHP method that is calling ::debug().
   */
  protected function debug($caller) {
    if (!$this->debuggerEnabled()) {
      return;
    }

    // Generate a caller name used both in logging and call counting.
    $caller = str_replace(
      $this->getClassName(__CLASS__),
      '',
      $this->getClassName($caller)
    );

    // Define a simple closure to print with prefixed indentation.
    $log = function($output) {
      $space = str_repeat('  ', count($this->debug));
      $this->logger()->debug($space . $output);
    };

    if (!in_array($caller, $this->debug)) {
      $this->debug[] = $caller;
      $log("--> $caller():");
    }
    else {
      unset($this->debug[array_search($caller, $this->debug)]);
      $log("      (finished)");
    }
  }

  /**
   * Determine whether $this->logger() has a RfcLogLevel::DEBUG grant.
   *
   * @return bool
   */
  protected function debuggerEnabled() {
    if (is_null($this->debug)) {
      if (in_array(RfcLogLevel::DEBUG, $this->logger()->getGrants())) {
        $this->debug = [];
      }
      else {
        $this->debug = FALSE;
        return;
      }
    }
    return is_array($this->debug);
  }

  /**
   * Generate a short and readable class name.
   *
   * @param string|object $class
   *   Fully namespaced class or an instantiated object.
   *
   * @return string
   */
  protected function getClassName($class) {
    if (is_object($class)) {
      $class = get_class($class);
    }
    if ($pos = strrpos($class, '\\')) {
      $class = substr($class, $pos + 1);
    }
    return $class;
  }

  /**
   * Retrieve request options used for all of Acquia Purge's balancer requests.
   *
   * @param array[] $extra
   *   Associative array of options to merge onto the standard ones.
   *
   * @return array
   */
  protected function getGlobalOptions(array $extra = []) {
    $opt = [
      // Disable exceptions for 4XX HTTP responses, those aren't failures to us.
      'http_errors' => FALSE,

      // Prevent inactive balancers from sucking all runtime up.
      'connect_timeout' => SELF::CONNECT_TIMEOUT,

      // Prevent unresponsive balancers from making Drupal slow.
      'timeout' => SELF::TIMEOUT,

      // Deliberately disable SSL verification to prevent unsigned certificates
      // from breaking down a website when purging a https:// URL!
      'verify' => FALSE,

      // Trigger \Drupal\acquia_purge\Http\LoadBalancerMiddleware which acts as
      // honest broker by throwing the right exceptions for our bal requests.
      'acquia_purge_middleware' => TRUE,
    ];
    return array_merge($opt, $extra);
  }

  /**
   * Concurrently execute the given requests.
   *
   * @param string $caller
   *   Name of the PHP method that is executing the requests.
   * @param \Closure $requests
   *   Generator yielding requests which will be passed to \GuzzleHttp\Pool.
   */
  protected function getResultsConcurrently($caller, $requests) {
    $this->debug(__METHOD__);
    $results = [];

    // Create a concurrently executed Pool which collects a boolean per request.
    $pool = new Pool($this->client, $requests(), [
      'options' => $this->getGlobalOptions(),
      'concurrency' => SELF::CONCURRENCY,
      'fulfilled' => function($response, $result_id) use (&$results) {
        $this->debug(__METHOD__ . '::fulfilled');
        $results[$result_id][] = TRUE;
      },
      'rejected' => function($reason, $result_id) use (&$results, $caller) {
        $this->debug(__METHOD__ . '::rejected');
        $results[$result_id][] = FALSE;
        $this->logFailedRequest($caller, $reason);
      },
    ]);

    // Initiate the transfers and create a promise.
    $promise = $pool->promise();

    // Force the pool of requests to complete.
    $promise->wait();

    $this->debug(__METHOD__);
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdealConditionsLimit() {
    // The max amount of outgoing HTTP requests that can be made during script
    // execution time. Although always respected as outer limit, it will be lower
    // in practice as PHP resource limits (max execution time) bring it further
    // down. However, the maximum amount of requests will be higher on the CLI.
    $balancers = count($this->hostingInfo->getBalancerAddresses());
    if ($balancers) {
      return intval(ceil(200 / $balancers));
    }
    return 100;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return TRUE;
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
    $this->debug(__METHOD__);

    // Set invalidation states to PROCESSING. Detect tags with spaces in them,
    // as space is the only character Drupal core explicitely forbids in tags.
    foreach ($invalidations as $invalidation) {
      $tag = $invalidation->getExpression();
      if (strpos($tag, ' ') !== FALSE) {
        $invalidation->setState(InvalidationInterface::FAILED);
        $this->logger->error(
          "Tag '%tag' contains a space, this is forbidden.", ['%tag' => $tag]
        );
      }
      else {
        $invalidation->setState(InvalidationInterface::PROCESSING);
      }
    }

    // Create grouped sets of 12 so that we can spread out the BAN load.
    $group = 0;
    $groups = [];
    foreach ($invalidations as $invalidation) {
      if ($invalidation->getState() !== InvalidationInterface::PROCESSING) {
        continue;
      }
      if (!isset($groups[$group])) {
        $groups[$group] = ['tags' => [], ['objects' => []]];
      }
      if (count($groups[$group]['tags']) >= 12) {
        $group++;
      }
      $groups[$group]['objects'][] = $invalidation;
      $groups[$group]['tags'][] = $invalidation->getExpression();
    }

    // Test if we have at least one group of tag(s) to purge, if not, bail.
    if (!count($groups)) {
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
      return;
    }

    // Now create requests for all groups of tags.
    $site = $this->hostingInfo->getSiteIdentifier();
    $ipv4_addresses = $this->hostingInfo->getBalancerAddresses();
    $requests = function() use ($groups, $ipv4_addresses, $site) {
      foreach ($groups as $group_id => $group) {
        $tags = implode(' ', Hash::cacheTags($group['tags']));
        foreach ($ipv4_addresses as $ipv4) {
          yield $group_id => function($poolopt) use ($site, $tags, $ipv4) {
            $opt = [
              'headers' => [
                'X-Acquia-Purge' => $site,
                'X-Acquia-Purge-Tags' => $tags,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Acquia Purge',
              ]
            ];
            if (is_array($poolopt) && count($poolopt)) {
              $opt = array_merge($poolopt, $opt);
            }
            return $this->client->requestAsync('BAN', "http://$ipv4/tags", $opt);
          };
        }
      }
    };

    // Execute the requests generator and retrieve the results.
    $results = $this->getResultsConcurrently('invalidateTags', $requests);

    // Triage the results and set all invalidation states correspondingly.
    foreach ($groups as $group_id => $group) {
      if ((!isset($results[$group_id])) || (!count($results[$group_id]))) {
        foreach ($group['objects'] as $invalidation) {
          $invalidation->setState(InvalidationInterface::FAILED);
        }
      }
      else {
        if (in_array(FALSE, $results[$group_id])) {
          foreach ($group['objects'] as $invalidation) {
            $invalidation->setState(InvalidationInterface::FAILED);
          }
        }
        else {
          foreach ($group['objects'] as $invalidation) {
            $invalidation->setState(InvalidationInterface::SUCCEEDED);
          }
        }
      }
    }

    $this->debug(__METHOD__);
  }

  /**
   * Invalidate a set of URL invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateUrls(array $invalidations) {
    $this->debug(__METHOD__);

    // Change all invalidation objects into the PROCESS state before kickoff.
    foreach ($invalidations as $inv) {
      $inv->setState(InvalidationInterface::PROCESSING);
    }

    // Generate request objects for each balancer/invalidation combination.
    $ipv4_addresses = $this->hostingInfo->getBalancerAddresses();
    $token = $this->hostingInfo->getBalancerToken();
    $requests = function() use ($invalidations, $ipv4_addresses, $token) {
      foreach ($invalidations as $inv) {
        foreach ($ipv4_addresses as $ipv4) {
          yield $inv->getId() => function($poolopt) use ($inv, $ipv4, $token) {
            $uri = $inv->getExpression();
            $host = parse_url($uri, PHP_URL_HOST);
            $uri = str_replace($host, $ipv4, $uri);
            $opt = [
              'headers' => [
                'X-Acquia-Purge' => $token,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Acquia Purge',
                'Host' => $host,
              ]
            ];
            if (is_array($poolopt) && count($poolopt)) {
              $opt = array_merge($poolopt, $opt);
            }
            return $this->client->requestAsync('PURGE', $uri, $opt);
          };
        }
      }
    };

    // Execute the requests generator and retrieve the results.
    $results = $this->getResultsConcurrently('invalidateUrls', $requests);

    // Triage the results and set all invalidation states correspondingly.
    foreach ($invalidations as $invalidation) {
      $inv_id = $invalidation->getId();
      if ((!isset($results[$inv_id])) || (!count($results[$inv_id]))) {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
      else {
        if (in_array(FALSE, $results[$inv_id])) {
          $invalidation->setState(InvalidationInterface::FAILED);
        }
        else {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
      }
    }

    $this->debug(__METHOD__);
  }

  /**
   * Invalidate URLs that contain the wildcard character "*".
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateWildcardUrls(array $invalidations) {
    $this->debug(__METHOD__);

    // Change all invalidation objects into the PROCESS state before kickoff.
    foreach ($invalidations as $inv) {
      $inv->setState(InvalidationInterface::PROCESSING);
    }

    // Generate request objects for each balancer/invalidation combination.
    $ipv4_addresses = $this->hostingInfo->getBalancerAddresses();
    $token = $this->hostingInfo->getBalancerToken();
    $requests = function() use ($invalidations, $ipv4_addresses, $token) {
      foreach ($invalidations as $inv) {
        foreach ($ipv4_addresses as $ipv4) {
          yield $inv->getId() => function($poolopt) use ($inv, $ipv4, $token) {
            $uri = $inv->getExpression();
            $host = parse_url($uri, PHP_URL_HOST);
            $uri = str_replace($host, $ipv4, $uri);
            $opt = [
              'headers' => [
                'X-Acquia-Purge' => $token,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Acquia Purge',
                'Host' => $host,
              ]
            ];
            if (is_array($poolopt) && count($poolopt)) {
              $opt = array_merge($poolopt, $opt);
            }
            return $this->client->requestAsync('BAN', $uri, $opt);
          };
        }
      }
    };

    // Execute the requests generator and retrieve the results.
    $results = $this->getResultsConcurrently('invalidateWildcardUrls', $requests);

    // Triage the results and set all invalidation states correspondingly.
    foreach ($invalidations as $invalidation) {
      $inv_id = $invalidation->getId();
      if ((!isset($results[$inv_id])) || (!count($results[$inv_id]))) {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
      else {
        if (in_array(FALSE, $results[$inv_id])) {
          $invalidation->setState(InvalidationInterface::FAILED);
        }
        else {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
      }
    }

    $this->debug(__METHOD__);
  }

  /**
   * Invalidate the entire website.
   *
   * This supports invalidation objects of the type 'everything'. Because many
   * load balancers on Acquia Cloud host multiple websites (e.g. sites in a
   * multisite) this will only affect the current site instance. This works
   * because all Varnish-cached resources are tagged with a unique identifier
   * coming from hostingInfo::getSiteIdentifier().
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateEverything(array $invalidations) {
    $this->debug(__METHOD__);

    // Set the 'everything' object(s) into processing mode.
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
    }

    // Fetch the site identifier and start with a successive outcome.
    $site_identifier = $this->hostingInfo->getSiteIdentifier();
    $overall_success = TRUE;

    // Synchronously request each balancer to wipe out everything for this site.
    foreach ($this->hostingInfo->getBalancerAddresses() as $ip_address) {
      try {
        $this->client->request('BAN', 'http://' . $ip_address . '/site', [
          'acquia_purge_middleware' => TRUE,
          'connect_timeout' => SELF::CONNECT_TIMEOUT,
          'http_errors' => FALSE,
          'timeout' => SELF::TIMEOUT,
          'headers' => [
            'X-Acquia-Purge' => $site_identifier,
            'Accept-Encoding' => 'gzip',
            'User-Agent' => 'Acquia Purge',
          ]
        ]);
      }
      catch (\Exception $e) {
        $this->logFailedRequest('invalidateEverything', $e);
        $overall_success = FALSE;
      }
    }

    // Set the object states according to our overall result.
    foreach ($invalidations as $invalidation) {
      if ($overall_success) {
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
      }
      else {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
    }

    $this->debug(__METHOD__);
  }

  /**
   * Write an error to the log for a failed request.
   *
   * @param string $caller
   *   Name of the PHP method that executed the request.
   * @param \Exception $e
   *   The exception thrown by Guzzle.
   */
  protected function logFailedRequest($caller, \Exception $e) {
    $msg = "::@caller() -> @class:";
    $vars = [
      '@caller' => $caller,
      '@class' => $this->getClassName($e),
      '@msg' => $e->getMessage(),
    ];

    // Add request information when this is present in the exception.
    if ($e instanceof RequestException) {
      $req = $e->getRequest();
      $msg .= " HTTP @status; @method @uri;";
      $vars['@uri'] = $req->getUri();
      $vars['@method'] = $req->getMethod();
      $vars['@status'] = $e->hasResponse() ? $e->getResponse()->getStatusCode() : '???';
    }

    // Log the normal message to the emergency output stream.
    $this->logger()->emergency("$msg @msg", $vars);

    // In debugging mode, follow the line with quite a bit more info.
    if ($this->debuggerEnabled()) {
      $l = function($m) {
        $this->logger()->debug(" - @debug", ['@debug' => $m]);
      };

      // Write out the full class name and lots of REQ/RSP data.
      $l('EXCEPTION    | ' . get_class($e));
      if ($e instanceof RequestException) {
        $l('REQ HTTP     | ' . $req->getProtocolVersion());
        $l('REQ URI      | ' . $req->getUri()->__toString());
        $l('REQ METHOD   | ' . $req->getMethod());
        foreach ($req->getHeaders() as $h => $v) {
          $l('REQ HEADERS  | ' . $h . ': ' . $req->getHeaderLine($h));
        }
        $l('RSP          | ' . ($e->hasResponse() ? 'YES' : 'No response'));
        if ($e->hasResponse() && ($rsp = $e->getResponse())) {
          $l('RSP HTTP     | ' . $rsp->getProtocolVersion());
          $l('RSP STATUS   |' . $rsp->getStatusCode());
          $l('RSP REASON   | ' . $rsp->getReasonPhrase());
          $l('RSP SUMMARY  | ' . json_encode($e->getResponseBodySummary($rsp)));
          foreach ($rsp->getHeaders() as $h => $v) {
            $l('RSP HEADERS  | ' . $h . ': ' . $rsp->getHeaderLine($h));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function routeTypeToMethod($type) {
    $methods = [
      'tag'         => 'invalidateTags',
      'url'         => 'invalidateUrls',
      'wildcardurl' => 'invalidateWildcardUrls',
      'everything'  => 'invalidateEverything'
    ];
    return isset($methods[$type]) ? $methods[$type] : 'invalidate';
  }

}
