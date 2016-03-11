<?php

/**
 * @file
 * Contains \Drupal\acquia_purge\Plugin\Purge\Purger\HttpPurger.
 */

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

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
 *   cooldown_time = 0.2,
 *   description = @Translation("Invalidates Varnish powered load balancers on your Acquia Cloud site."),
 *   multi_instance = FALSE,
 *   types = {"url"},
 * )
 */
class AcquiaCloudPurger extends PurgerBase implements PurgerInterface {

  /**
   * The number of HTTP requests executed in parallel during purging.
   */
  const PARALLEL_REQUESTS = 6;

  /**
   * The number of seconds before a purge attempt times out.
   */
  const REQUEST_TIMEOUT = 2;

  /**
   * @var \Drupal\acquia_purge\HostingInfoInterface
   */
  protected $acquiaPurgeHostinginfo;

  /**
   * Constructs a AcquiaCloudPurger object.
   *
   * @param \Drupal\acquia_purge\HostingInfoInterface $acquia_purge_hostinginfo
   *   Technical information accessors for the Acquia Cloud environment.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  function __construct(HostingInfoInterface $acquia_purge_hostinginfo, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->acquiaPurgeHostingInfo = $acquia_purge_hostinginfo;
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
  public function getIdealConditionsLimit() {
    // The max amount of outgoing HTTP requests that can be made during script
    // execution time. Although always respected as outer limit, it will be lower
    // in practice as PHP resource limits (max execution time) bring it further
    // down. However, the maximum amount of requests will be higher on the CLI.
    return 100;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {
    return 4.0;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    throw new \Exception("Malum consilium quod mutari non potest ");

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

  /**
   * Invalidate a set of tag invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateTags(array $invalidations) {
    throw new \Exception(__METHOD__);
  }

  /**
   * Invalidate a set of URL invalidations.
   *
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate()
   * @see \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::routeTypeToMethod()
   */
  public function invalidateUrls(array $invalidations) {
    throw new \Exception(__METHOD__);
  }

  // /**
  //  * Purge a single path on all domains and load balancers.
  //  *
  //  * @param string $path
  //  *   The Drupal path (for example: '<front>', 'user/1' or a alias).
  //  *
  //  * @warning
  //  *   This is the core HTTP purge implementation for a single path item, and is
  //  *   NOT TO BE CALLED DIRECTLY! Instead AcquiaPurgeService has to be utilized
  //  *   which takes care of queuing, statistics, capacity calculation and several
  //  *   other safety checks and balances. This is a example of how to do this:
  //  *   $queue = _acquia_purge_service();
  //  *   $queue->addPaths(array('path/1', 'path/2', 'path3'));
  //  *   $queue->process();
  //  *
  //  * @return true|false
  //  *   Boolean TRUE/FALSE indicating success or failure of the attempt.
  //  */
  // function _acquia_purge_purge($path) {
  //
  //   // Ask our built-in diagnostics system to preliminary find issues that are so
  //   // risky we can expect problems. Everything with ACQUIA_PURGE_SEVLEVEL_ERROR
  //   // will cause purging to cease and log messages to be written. Because we
  //   // return FALSE, the queued items will be purged later in better weather.
  //   if (count($err = _acquia_purge_get_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR))) {
  //     _acquia_purge_get_diagnosis_logged($err);
  //     return FALSE;
  //   }
  //
  //   // Fetch and statically store the base path.
  //   static $base_path;
  //   if (is_null($base_path)) {
  //     $base_path = _acquia_purge_variable('acquia_purge_base_path');
  //   }
  //
  //   // Determine the request token, this makes up the X-Acquia-Purge header.
  //   static $request_token;
  //   if (is_null($request_token)) {
  //     if ($token_configured = _acquia_purge_variable('acquia_purge_token')) {
  //       $request_token = (string) $token_configured;
  //     }
  //     else {
  //       $request_token = _acquia_purge_get_site_name();
  //     }
  //   }
  //
  //   // Because a single path can exist on http://, https://, on various domain
  //   // names and could be cached on any of the known load balancers. Therefore we
  //   // define a list of HTTP requests that we are going to fire in a moment.
  //   $requests = array();
  //   foreach (_acquia_purge_get_balancers() as $balancer_ip) {
  //     foreach (_acquia_purge_get_domains() as $domain) {
  //       foreach (_acquia_purge_get_protocol_schemes() as $scheme) {
  //         $rqst = new stdClass();
  //         $rqst->scheme = $scheme;
  //         $rqst->rtype = 'PURGE';
  //         $rqst->balancer = $balancer_ip;
  //         $rqst->domain = $domain;
  //         $rqst->path = str_replace('//', '/', $base_path . $path);
  //         $rqst->uri = $rqst->scheme . '://' . $rqst->domain . $rqst->path;
  //         $rqst->uribal = $rqst->scheme . '://' . $rqst->balancer . $rqst->path;
  //         $rqst->headers = array(
  //           'Host: ' . $rqst->domain,
  //           'Accept-Encoding: gzip',
  //           'X-Acquia-Purge: ' . $request_token,
  //         );
  //         $requests[] = $rqst;
  //       }
  //     }
  //   }
  //
  //   // Before we issue these purges against the load balancers we ensure that any
  //   // of these URLs are not left cached in Drupal's ordinary page cache.
  //   $already_cleared = array();
  //   foreach ($requests as $rqst) {
  //     if (!in_array($rqst->uri, $already_cleared)) {
  //       cache_clear_all($rqst->uri, 'cache_page');
  //       $already_cleared[] = $rqst->uri;
  //     }
  //   }
  //
  //   // Execute the prepared requests efficiently and log their results.
  //   $overall_success = TRUE;
  //   foreach (_acquia_purge_purge_requests($requests) as $rqst) {
  //     if ($rqst->result == TRUE) {
  //       if (_acquia_purge_variable('acquia_purge_log_success') === TRUE) {
  //         watchdog(
  //           'acquia_purge',
  //           "Purged '%url' from load balancer %balancer.",
  //           array('%url' => $rqst->uri, '%balancer' => $rqst->balancer),
  //           WATCHDOG_INFO);
  //       }
  //       _acquia_purge_service()->history($rqst->uri);
  //     }
  //     else {
  //       if ($overall_success) {
  //         $overall_success = FALSE;
  //       }
  //
  //       // Write the failure to watchdog and be as descriptive as we can.
  //       switch ($rqst->error_curl) {
  //         case CURLE_COULDNT_CONNECT:
  //           $msg = "Cannot connect to %bal:80, '%path' goes back to queue!";
  //           break;
  //
  //         case CURLE_COULDNT_RESOLVE_HOST:
  //           $msg = "Cannot resolve host %bal, '%path' goes back to queue!";
  //           break;
  //
  //         case CURLE_OPERATION_TIMEOUTED:
  //           $msg = "Connecting to %bal exceeded %timeout seconds, '%path'"
  //             . ' goes back to queue!';
  //           break;
  //
  //         case CURLE_URL_MALFORMAT:
  //           $msg = "Cannot purge malformed URL '%uri', '%path' goes back to"
  //             . ' queue! DEBUG: %debug';
  //           break;
  //
  //         default:
  //           $msg = "Failed purging '%uri' from %bal, '%path' goes back to queue!"
  //             . ' CURL: %curl; DEBUG: %debug';
  //           break;
  //       }
  //       watchdog('acquia_purge', $msg,
  //         array(
  //           '%uri' => $rqst->uri,
  //           '%bal' => $rqst->balancer,
  //           '%path' => $rqst->path,
  //           '%curl' => (string) curl_strerror($rqst->error_curl),
  //           '%debug' => $rqst->error_debug,
  //           '%timeout' => ACQUIA_PURGE_REQUEST_TIMEOUT,
  //         ), WATCHDOG_ERROR);
  //     }
  //   }
  //
  //   // If one the many HTTP requests failed we treat the full path as a failure,
  //   // by sending back FALSE the item will remain in the queue. Failsafe style.
  //   return $overall_success;
  // }
  //
  // /**
  //  * Process the HTTP requests for a single purge.
  //  *
  //  * @param string $requests
  //  *   Unassociative array (list) of simple Stdclass objects with the following
  //  *   properties: scheme, rtype, server, domain, path, uri, uribal.
  //  *
  //  * @see _acquia_purge_purge()
  //  *
  //  * @return array
  //  *   The given requests array with added properties that describe the result of
  //  *   the request: 'result', 'error_curl', 'error_http', 'error_debug'.
  //  */
  // function _acquia_purge_purge_requests($requests) {
  //   $single_mode = (count($requests) === 1);
  //   $results = array();
  //
  //   // Initialize the cURL multi handler.
  //   if (!$single_mode) {
  //     static $curl_multi;
  //     if (is_null($curl_multi)) {
  //       $curl_multi = curl_multi_init();
  //     }
  //   }
  //
  //   // Enter our event loop and keep on requesting until $unprocessed is empty.
  //   $unprocessed = count($requests);
  //   while ($unprocessed > 0) {
  //
  //     // Group requests per sets that we can run in parallel.
  //     for ($i = 0; $i < ACQUIA_PURGE_PARALLEL_REQUESTS; $i++) {
  //       if ($rqst = array_shift($requests)) {
  //         $rqst->curl = curl_init();
  //
  //         // Instantiate the cURL resource and configure its runtime parameters.
  //         curl_setopt($rqst->curl, CURLOPT_URL, $rqst->uribal);
  //         curl_setopt($rqst->curl, CURLOPT_TIMEOUT, ACQUIA_PURGE_REQUEST_TIMEOUT);
  //         curl_setopt($rqst->curl, CURLOPT_HTTPHEADER, $rqst->headers);
  //         curl_setopt($rqst->curl, CURLOPT_CUSTOMREQUEST, $rqst->rtype);
  //         curl_setopt($rqst->curl, CURLOPT_FAILONERROR, TRUE);
  //         curl_setopt($rqst->curl, CURLOPT_RETURNTRANSFER, TRUE);
  //
  //         // For SSL purging, we disable SSL host and peer verification. Although
  //         // this triggers red flags to the security concerned user, this avoids
  //         // purges to fail on sites with self-signed certificates. All we request
  //         // the remote balancer is to wipe items from its cache after all.
  //         if ($rqst->scheme === 'https') {
  //           curl_setopt($rqst->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
  //           curl_setopt($rqst->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
  //         }
  //
  //         // Add our handle to the multiple cURL handle.
  //         if (!$single_mode) {
  //           curl_multi_add_handle($curl_multi, $rqst->curl);
  //         }
  //
  //         // Add the shifted request to the results array and change the counter.
  //         $results[] = $rqst;
  //         $unprocessed--;
  //       }
  //     }
  //
  //     // Execute the created handles in parallel.
  //     if (!$single_mode) {
  //       $active = NULL;
  //       do {
  //         $mrc = curl_multi_exec($curl_multi, $active);
  //       } while ($mrc == CURLM_CALL_MULTI_PERFORM);
  //       while ($active && $mrc == CURLM_OK) {
  //         if (curl_multi_select($curl_multi) != -1) {
  //           do {
  //             $mrc = curl_multi_exec($curl_multi, $active);
  //           } while ($mrc == CURLM_CALL_MULTI_PERFORM);
  //         }
  //       }
  //     }
  //
  //     // In single mode there's only one request to do, use curl_exec().
  //     else {
  //       curl_exec($results[0]->curl);
  //       $single_info = array('result' => curl_errno($results[0]->curl));
  //     }
  //
  //     // Iterate the set of results and fetch cURL result and resultcodes. Only
  //     // process those with the 'curl' property as the property will be removed.
  //     foreach ($results as $i => $rqst) {
  //       if (!isset($rqst->curl)) {
  //         continue;
  //       }
  //       $info = $single_mode ? $single_info : curl_multi_info_read($curl_multi);
  //       $results[$i]->result = ($info['result'] == CURLE_OK) ? TRUE : FALSE;
  //       $results[$i]->error_curl = $info['result'];
  //       $results[$i]->error_http = curl_getinfo($rqst->curl, CURLINFO_HTTP_CODE);
  //
  //       // Curl hasn't proven to be always as reliable when it comes to result
  //       // reporting, and therefore we enforce success whenever the HTTP codes
  //       // are 200 or 404, which is Varnish-talk for 'things are good my friend'.
  //       if (in_array($results[$i]->error_http, array(404, 200))) {
  //         $results[$i]->result = TRUE;
  //       }
  //
  //       // Collect debugging information if necessary.
  //       $results[$i]->error_debug = '';
  //       if (!$results[$i]->result) {
  //         $debug = curl_getinfo($rqst->curl);
  //         $debug['headers'] = implode('|', $rqst->headers);
  //         unset($debug['certinfo']);
  //         $results[$i]->error_debug = _acquia_purge_export_debug_symbols($debug);
  //       }
  //
  //       // Remove the handle if parallel processing occurred.
  //       if (!$single_mode) {
  //         curl_multi_remove_handle($curl_multi, $rqst->curl);
  //       }
  //
  //       // Close the resource and delete its property.
  //       curl_close($rqst->curl);
  //       unset($rqst->curl);
  //     }
  //   }
  //
  //   return $results;
  // }

  /**
   * Route certain type of invalidations to other methods.
   *
   * Simple purgers supporting just one type - for example 'tag' - will get that
   * specific type offered in ::invalidate(). However, when supporting multiple
   * types it might be useful to have PurgersService sort and route these for
   * you to the methods you specify. The expected signature and method behavior
   * is equal to \Drupal\purge\Plugin\Purge\Purger\PurgerInterface::invalidate.
   *
   * One note of warning: depending on the implementation specifics of a plugin,
   * sorting and dispatching types to different code paths can be less efficient
   * compared to external platforms allowing you to mix and send everyhing in
   * one single batch. Therefore, consult the API of the platform your plugin
   * supports to decide what the most efficient implementation will be.
   *
   * A simple implementation will look like this:
   * @code
   *   public function routeTypeToMethod($type) {
   *     $methods = [
   *       'path' => 'invalidatePaths',
   *       'tag'  => 'invalidateTags',
   *       'url'  => 'invalidateUrls',
   *     ];
   *     return isset($methods[$type]) ? $methods[$type] : 'invalidate';
   *   }
   * @endcode
   *
   * @param string $type
   *   The type of invalidation(s) about to be offered to the purger.
   *
   * @return string
   *   The PHP method name called on the purger with a $invalidations parameter.
   */
  public function routeTypeToMethod($type) {
    $methods = [
      'tag'  => 'invalidateTags',
      'url'  => 'invalidateUrls',
    ];
    return isset($methods[$type]) ? $methods[$type] : 'invalidate';
  }

}
