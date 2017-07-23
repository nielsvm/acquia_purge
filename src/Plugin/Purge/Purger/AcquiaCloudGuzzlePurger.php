<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request as symfonyRequest;
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
   * Ensure that the request object has no trusted hosts configured.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object used for communicating with ::executeRequests(), which
   *   uses cUrl directly. NEVER use this method for the general request object
   *   or request objects actively fed to Guzzle or other APIs!
   *
   * @return void
   */
  protected function disableTrustedHostsMechanism(symfonyRequest $request) {
    // $trusted_hosts = [];
    // foreach ($request->getTrustedHosts() as $pattern) {
    //   $trusted_hosts[] = ltrim(rtrim($pattern, '#i'), '#');
    // }
    // $trusted_hosts = array_merge(
    //   $this->hostingInfo->getBalancerAddresses(),
    //   $trusted_hosts
    // );
    // $request->setTrustedHosts($trusted_hosts);
    $request->setTrustedHosts([]);
  }

  /**
   * Execute a set of HTTP requests.
   *
   * Executes a set of HTTP requests using the cUrl PHP extension and adds
   * resulting information to the ->attributes parameter bag on each request
   * object. It will perform parallel processing to reduce the PHP execution
   * time taken.
   *
   * @param \Symfony\Component\HttpFoundation\Request[] $requests
   *   Unassociative list of Request objects to execute. When the 'connect_to'
   *   attribute key is present, this value will be used to connect to instead
   *   of the 'host' header.
   *
   * @return void
   */
  protected function executeRequests(array $requests) {

    // Presort the request objects in request groups based on the maximum amount
    // of requests we can perform in parallel. Max SELF::CONCURRENCY each!
    $request_groups = [];
    $unprocessed = count($requests);
    reset($requests);
    while ($unprocessed > 0) {
      $group = [];
      for ($n = 0; $n < SELF::CONCURRENCY; $n++) {
        if (!is_null($i = key($requests))) {
          $group[] = $requests[$i];
          $unprocessed--;
          next($requests);
        }
      }
      if (count($group)) {
        $request_groups[] = $group;
      }
    }

    // Perform HTTP processing for each request group.
    foreach ($request_groups as $group) {
      $multihandler = (count($group) === 1) ? FALSE : curl_multi_init();

      // Prepare the cUrl handlers for each Request.
      foreach ($group as $r) {
        $handler = curl_init();
        curl_setopt($handler, CURLOPT_TIMEOUT, SELF::TIMEOUT);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, $r->getMethod());
        curl_setopt($handler, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, TRUE);
        $r->attributes->set('curl_handler', $handler);

        // Confgure the URL to connect to on the handler.
        $url = $r->getUri();
        if ($connect_to = $r->attributes->get('connect_to')) {
          $url = str_replace($r->getHttpHost(), $connect_to, $url);
        }
        curl_setopt($handler, CURLOPT_URL, $url);

        // Generate and set the list of headers to send.
        $headers = [];
        foreach (explode("\r\n", trim($r->headers->__toString())) as $line) {
          $headers[] = $line;
        }
        curl_setopt($handler, CURLOPT_HTTPHEADER, $headers);

        // For requests over SSL, we disable host and peer verification. This
        // is usually a red flag to the security concerned, but avoids a great
        // deal of trouble with self-signed certificates. Above all, this is
        // only used for external cache invalidation.
        if ($r->isSecure()) {
          curl_setopt($handler, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, FALSE);
        }

        // With parallel processing, add this resource to the multihandler.
        if (is_resource($multihandler)) {
          curl_multi_add_handle($multihandler, $handler);
        }
      }

      // Let cUrl execute the requests (single mode or multihandling).
      if (is_resource($multihandler)) {
        $active = NULL;
        do {
          $mrc = curl_multi_exec($multihandler, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
          if (curl_multi_select($multihandler) != -1) {
            do {
              $mrc = curl_multi_exec($multihandler, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
          }
        }
      }
      else {
        $handler = $group[0]->attributes->get('curl_handler');
        curl_exec($handler);
        $single_info = ['result' => curl_errno($handler)];
      }

      // Query the handlers to put the results as attributes onto the request.
      foreach ($group as $r) {
        if (!($handler = $r->attributes->get('curl_handler'))) {
          continue;
        }

        // Set the general request results as attributes to the request.
        if (is_resource($multihandler)) {
          $info = curl_multi_info_read($multihandler);
        }
        else {
          $info = $single_info;
        }
        $r->attributes->set('curl_result', $info['result']);
        $r->attributes->set('curl_result_ok', $info['result'] == CURLE_OK);

        // Add all other cUrl information as attributes to the request.
        foreach (curl_getinfo($handler) as $key => $value) {
          $r->attributes->set('curl_' . $key, $value);
        }

        // Remove all cUrl resources except the results of course.
        if (is_resource($multihandler)) {
          curl_multi_remove_handle($multihandler, $handler);
        }
        curl_close($handler);
        $r->attributes->remove('curl_handler');
      }
    }
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
    $this->logger->debug('::getResultsConcurrently() starting');
    $results = [];

    // Create a concurrently executed Pool which collects a boolean per request.
    $pool = new Pool($this->client, $requests(), [
      'options' => $this->getGlobalOptions(),
      'concurrency' => SELF::CONCURRENCY,
      'fulfilled' => function($response, $result_id) use (&$results) {
        $results[$result_id][] = TRUE;
      },
      'rejected' => function($reason, $result_id) use (&$results, $caller) {
        $results[$result_id][] = FALSE;
        $this->logFailedRequest($caller, $reason);
      },
    ]);

    // Initiate the transfers and create a promise.
    $promise = $pool->promise();

    // Force the pool of requests to complete.
    $promise->wait();

    $this->logger->debug('::getResultsConcurrently() finished');
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
    $this->logger->debug('::invalidateTags() starting');

    // Collect tags and set all states to PROCESSING before we kick off.
    $tags = [];
    $hashes = [];
    foreach ($invalidations as $invalidation) {
      $expression = $invalidation->getExpression();

      // Detect tags with spaces in it. This is the only character Drupal core
      // forbids explicitely to be used in tags, as we're using it as separator
      // for multiple tags.
      if (strpos($expression, ' ') !== FALSE) {
        $invalidation->setState(InvalidationInterface::FAILED);
        $this->logger->error(
          "The tag '%tag' contains a space, this is forbidden.",
          [
            '%tag' => $expression,
          ]
        );
      }
      else {
        $invalidation->setState(InvalidationInterface::PROCESSING);
        $tags[] = $expression;
      }
    }

    // Test if we have at least one tag to purge, if not, bail.
    if (!count($tags)) {
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::FAILED);
      }
      return;
    }

    // Predescribe the requests to make.
    $requests = [];
    $tags_hashed = implode(' ', Hash::cacheTags($tags));
    $site_identifier = $this->hostingInfo->getSiteIdentifier();
    foreach ($this->hostingInfo->getBalancerAddresses() as $ip_address) {
      $r = symfonyRequest::create("http://$ip_address/tags", 'BAN');
      $this->disableTrustedHostsMechanism($r);
      $r->headers->set('X-Acquia-Purge', $site_identifier);
      $r->headers->set('X-Acquia-Purge-Tags', $tags_hashed);
      $r->headers->remove('Accept-Language');
      $r->headers->remove('Accept-Charset');
      $r->headers->remove('Accept');
      $r->headers->set('Accept-Encoding', 'gzip');
      $r->headers->set('User-Agent', 'Acquia Purge');
      $requests[] = $r;
    }

    // Perform the requests, results will be set as attributes onto the objects.
    $this->executeRequests($requests);

    // Collect all results per invalidation object based on the cUrl data.
    $overall_success = TRUE;
    foreach ($requests as $request) {
      if ($request->attributes->get('curl_http_code') !== 200) {
        $overall_success = FALSE;
        $this->logFailedRequestLegacy($request);
      }
    }

    // Set the object states according to our overall result.
    foreach ($invalidations as $invalidation) {
      if ($invalidation->getState() === InvalidationInterface::PROCESSING) {
        if ($overall_success) {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }
        else {
          $invalidation->setState(InvalidationInterface::FAILED);
        }
      }
    }

    $this->logger->debug('::invalidateTags() finished');
  }

  /**
   * Invalidate a set of URL invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateUrls(array $invalidations) {
    $this->logger->debug('::invalidateUrls() starting');

    // Change all invalidation objects into the PROCESS state before kickoff.
    foreach ($invalidations as $inv) {
      $inv->setState(InvalidationInterface::PROCESSING);
    }

    // Generate request objects for each balancer/invalidation combination.
    $ipv4_addresses = $this->hostingInfo->getBalancerAddresses();
    $token = $this->hostingInfo->getBalancerToken();
    $requests = function () use ($invalidations, $ipv4_addresses, $token) {
      foreach ($invalidations as $inv) {
        foreach ($ipv4_addresses as $ipv4) {
          yield $inv->getId() => function ($poolopt) use ($inv, $ipv4, $token) {
            $uri = $inv->getExpression();
            $host = parse_url($uri, PHP_URL_HOST);
            $uri = str_replace($host, $ipv4, $uri);
            $options = [
              'headers' => [
                'X-Acquia-Purge' => $token,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Acquia Purge',
                'Host' => $host,
              ]
            ];
            if (is_array($poolopt) && count($poolopt)) {
              $options = array_merge($poolopt, $options);
            }
            return $this->client->requestAsync('PURGE', $uri, $options);
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

    $this->logger->debug('::invalidateUrls(): finished');
  }

  /**
   * Invalidate URLs that contain the wildcard character "*".
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateWildcardUrls(array $invalidations) {
    $this->logger->debug('::invalidateWildcardUrls() starting');

    // Change all invalidation objects into the PROCESS state before kickoff.
    foreach ($invalidations as $inv) {
      $inv->setState(InvalidationInterface::PROCESSING);
    }

    // Generate request objects for each balancer/invalidation combination.
    $ipv4_addresses = $this->hostingInfo->getBalancerAddresses();
    $token = $this->hostingInfo->getBalancerToken();
    $requests = function () use ($invalidations, $ipv4_addresses, $token) {
      foreach ($invalidations as $inv) {
        foreach ($ipv4_addresses as $ipv4) {
          yield $inv->getId() => function ($poolopt) use ($inv, $ipv4, $token) {
            $uri = $inv->getExpression();
            $host = parse_url($uri, PHP_URL_HOST);
            $uri = str_replace($host, $ipv4, $uri);
            $options = [
              'headers' => [
                'X-Acquia-Purge' => $token,
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'Acquia Purge',
                'Host' => $host,
              ]
            ];
            if (is_array($poolopt) && count($poolopt)) {
              $options = array_merge($poolopt, $options);
            }
            return $this->client->requestAsync('BAN', $uri, $options);
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

    $this->logger->debug('::invalidateWildcardUrls(): finished');
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
    $this->logger->debug('::invalidateEverything() starting');

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

    $this->logger->debug('::invalidateEverything() finished');
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
    $vars = ['@caller' => $caller, '@msg' => $e->getMessage()];
    $msg = "::@caller() -> @class:";

    // Get the short class name of the thrown exception.
    $vars['@class'] = get_class($e);
    if ($e_class_pos = strrpos($vars['@class'], '\\')) {
      $vars['@class'] = substr($vars['@class'], $e_class_pos + 1);
    }

    // Add request information when this is present in the exception.
    if ($e instanceof RequestException) {
      $req = $e->getRequest();
      $msg .= " HTTP @status; @method @uri;";
      $vars['@uri'] = $req->getUri();
      $vars['@method'] = $req->getMethod();
      $vars['@status'] = $e->hasResponse() ? $e->getResponse()->getStatusCode() : '???';
    }

    $this->logger()->emergency("$msg @msg", $vars);
  }

  /**
   * Write an error to the log for a failed request.
   *
   * Writes messages to the logs after requests passed through ::executeRequests
   * and didn't pass invalidation-type specific requirements. The messages are
   * as human-readable as possible, with debugging symbols as last resort.
   *
   * @param \Symfony\Component\HttpFoundation\Request $r
   *   The request object, after it passed through ::executeRequests()..
   *
   * @return void
   */
  protected function logFailedRequestLegacy(symfonyRequest $r) {
    $msg = 'Failed %method to %uri%urisuffix: ';
    $vars = [
      "%method" => $r->getMethod(),
      "%timeout" => SELF::TIMEOUT,
      "%uri" => $r->attributes->get('curl_url'),
      "%urisuffix" => $r->attributes->get('connect_to') ? sprintf(" (host=%s)", $r->getHttpHost()) : '',
      "%curl_total_time" => var_export($r->attributes->get('curl_total_time'), TRUE),
    ];
    switch ($r->attributes->get('curl_result')) {
      case CURLE_COULDNT_CONNECT:
        $msg .= "couldn't connect.";
        break;

      case CURLE_COULDNT_RESOLVE_HOST:
        $msg .= "unable to resolve host.";
        break;

      case CURLE_OPERATION_TIMEOUTED:
        $msg .= "timed out: timeout=%timeout, total_time=%curl_total_time.";
        break;

      case CURLE_URL_MALFORMAT:
        $msg .= "URL malformatted!";
        break;

      default:
        $msg .= "unknown, debugging info (JSON): %debug";
        $vars['%debug'] = str_replace('curl_', '', json_encode(current((array) $r->attributes)));
        break;
    }
    $this->logger->error($msg, $vars);
    $this->logger->debug("REQHEADERS= %v", ['%v' => json_encode(current((array) $r->headers))]);
    $this->logger->debug("CONTENT= %v", ['%v' => $r->getContent()]);
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
