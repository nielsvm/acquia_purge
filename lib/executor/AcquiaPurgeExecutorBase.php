<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorBase.
 */

/**
 * Provides an executor, which is responsible for taking a set of invalidation
 * objects and wiping these paths/URLs from an external cache.
 */
abstract class AcquiaPurgeExecutorBase implements AcquiaPurgeExecutorInterface {

  /**
   * The invalidation class to instantiate invalidation objects from.
   *
   * @var string
   */
  protected $class_request;

  /**
   * The unique identifier for this executor.
   *
   * @var string
   */
  protected $id;

  /**
   * Whether to log successes or not.
   *
   * @var bool
   */
  protected $log_successes;

  /**
   * The Acquia Purge service object.
   *
   * @var AcquiaPurgeService
   */
  protected $service;

  /**
   * Construct a new AcquiaPurgeExecutorBase instance.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service object.
   */
  public function __construct(AcquiaPurgeService $service) {
    $this->id = get_class($this);
    $this->service = $service;
    $this->log_successes = _acquia_purge_variable('acquia_purge_log_success');
    $this->class_request = _acquia_purge_load(
      array(
        '_acquia_purge_executor_request_interface',
        '_acquia_purge_executor_request'
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequest($uri = NULL) {
    return new $this->class_request($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function requestsExecute($requests, $no_ssl_verify = FALSE) {
    $single_mode = (count($requests) === 1);
    $processed = array();

    // Initialize the cURL multi handler.
    if (!$single_mode) {
      static $curl_multi;
      if (is_null($curl_multi)) {
        $curl_multi = curl_multi_init();
      }
    }

    // Enter our event loop and keep on requesting until $unprocessed is empty.
    $unprocessed = count($requests);
    while ($unprocessed > 0) {

      // Group requests per sets that we can run in parallel.
      for ($i = 0; $i < ACQUIA_PURGE_PARALLEL_REQUESTS; $i++) {
        if ($r = array_shift($requests)) {
          $r->curl = curl_init();

          // Instantiate the cURL resource and configure its runtime parameters.
          curl_setopt($r->curl, CURLOPT_URL, $r->uri);
          curl_setopt($r->curl, CURLOPT_TIMEOUT, ACQUIA_PURGE_REQUEST_TIMEOUT);
          curl_setopt($r->curl, CURLOPT_HTTPHEADER, $r->headers);
          curl_setopt($r->curl, CURLOPT_CUSTOMREQUEST, $r->method);
          curl_setopt($r->curl, CURLOPT_FAILONERROR, TRUE);
          curl_setopt($r->curl, CURLOPT_RETURNTRANSFER, TRUE);

          // For SSL purging, we disable SSL host and peer verification. This
          // should trigger red flags to the security concerned user, but it
          // also avoids purges to fail on sites with self-signed certs. This
          // therefore is a risk worth taking in return for a better user
          // experience as compromised cache invalidation requests couldn't
          // cause much harm anyway.
          if ($no_ssl_verify && ($r->scheme === 'https')) {
            curl_setopt($r->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($r->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
          }

          // Add our handle to the multiple cURL handle.
          if (!$single_mode) {
            curl_multi_add_handle($curl_multi, $r->curl);
          }

          $processed[] = $r;
          $unprocessed--;
        }
      }

      // Execute the created handles in parallel.
      if (!$single_mode) {
        $active = NULL;
        do {
          $mrc = curl_multi_exec($curl_multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
          if (curl_multi_select($curl_multi) != -1) {
            do {
              $mrc = curl_multi_exec($curl_multi, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
          }
        }
      }

      // In single mode there's only one request to do, use curl_exec().
      else {
        curl_exec($processed[0]->curl);
        $single_info = array('result' => curl_errno($processed[0]->curl));
      }

      // Iterate the set of results and fetch cURL result and resultcodes. Only
      // process those with the 'curl' property as the property will be removed.
      foreach ($processed as $i => $r) {
        if (!isset($r->curl)) {
          continue;
        }
        $info = $single_mode ? $single_info : curl_multi_info_read($curl_multi);
        $processed[$i]->result = ($info['result'] == CURLE_OK) ? TRUE : FALSE;
        $processed[$i]->error_curl = $info['result'];
        $processed[$i]->response_code = curl_getinfo($r->curl, CURLINFO_HTTP_CODE);

        // Collect debugging information if necessary.
        $processed[$i]->error_debug = '';
        if (!$processed[$i]->result) {
          $debug = curl_getinfo($r->curl);
          $debug['headers'] = implode('|', $r->headers);
          unset($debug['certinfo']);
          $processed[$i]->error_debug = _acquia_purge_export_debug_symbols($debug);
        }

        // Remove the handle if parallel processing occurred.
        if (!$single_mode) {
          curl_multi_remove_handle($curl_multi, $r->curl);
        }

        curl_close($r->curl);
        $r->curl = NULL;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestsLog($requests, $consequence = 'goes back to queue!') {
    $id = $this->getId();
    foreach ($requests as $r) {
      $vars = array(
        '%id' => $id,
        '%uri' => $r->uri,
        '%host' => parse_url($r->uri, PHP_URL_HOST),
        '%method' => $r->method,
        '%response_code' => $r->response_code,
      );
      if (isset($r->_host)) {
        $vars['%uri'] = sprintf("%s (host=%s)", $r->uri, $r->_host);
      }

      // Log success or failure, depending on $r->result.
      if ($r->result) {
        if ($this->log_successes) {
          watchdog(
            'acquia_purge',
            "%id: %uri succeeded (%method, %response_code).",
            $vars,
            WATCHDOG_INFO
          );
        }
      }
      else {
        $vars['%path'] = $r->path;
        $vars['%curl'] = (string) curl_strerror($r->error_curl);
        $vars['%debug'] = $r->error_debug;
        $vars['%timeout'] = ACQUIA_PURGE_REQUEST_TIMEOUT;
        switch ($r->error_curl) {
          case CURLE_COULDNT_CONNECT:
            $msg = "%id: unable to connect to %host, ";
            $msg .= $consequence;
            break;

          case CURLE_COULDNT_RESOLVE_HOST:
            $msg = "%id: cannot resolve host for %uri, ";
            $msg .= $consequence;
            break;

          case CURLE_OPERATION_TIMEOUTED:
            $msg = "%id: %uri exceeded %timeout sec., ";
            $msg .= $consequence;
            break;

          case CURLE_URL_MALFORMAT:
            $msg = "%id: %uri failed: URL malformed, ";
            $msg .= $consequence;
            $msg .= ' DEBUG: %debug';
            break;

          default:
            $msg = "%id: %uri failed, ";
            $msg .= $consequence;
            $msg .= ' CURL: %curl; DEBUG: %debug';
            break;
        }
        watchdog('acquia_purge', $msg, $vars, WATCHDOG_ERROR);
      }
    }
  }

}
