<?php

/**
 * @file
 * Contains AcquiaPurgeCapacity.
 */

/**
 * Runtime capacity tracker.
 */
class AcquiaPurgeCapacity {

  /**
   * The maximum number of queue items that can be processed during runtime.
   */
  const QUEUE_CLAIMS_LIMIT = 100;

  /**
   * Maximum number of HTTP requests.
   *
   * The max amount of outgoing HTTP requests that can be made during script
   * execution time. Although always respected as outer limit, it will be lower
   * in practice as PHP resource limits (max execution time) bring it further
   * down. However, the maximum amount of requests will be higher on the CLI.
   */
  const HTTP_REQUESTS_LIMIT = 100;

  /**
   * How many seconds a single HTTP request can take (worst case).
   *
   * @see AcquiaPurgeExecutorInterface::requestsExecute()
   */
  const HTTP_REQUEST_TIMEOUT = 2;

  /**
   * How many HTTP requests are executed in parallel.
   *
   * @see AcquiaPurgeExecutorInterface::requestsExecute()
   */
  const HTTP_PARALLEL_REQUESTS = 6;

  /**
   * The number of expected HTTP requests per single queue item.
   *
   * @var int
   */
  protected $httpRequestsFactor = NULL;

  /**
   * The number of HTTP requests that executors are allowed to make.
   *
   * @var int
   */
  protected $httpRequestsLimit = NULL;

  /**
   * The number of queue items that can be processed during runtime.
   *
   * @var int
   */
  protected $queueClaimsLimit = NULL;

  /**
   * The Acquia Purge service object.
   *
   * @var AcquiaPurgeService
   */
  protected $service;

  /**
   * Construct a new AcquiaPurgeCapacity instance.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service object.
   */
  public function __construct(AcquiaPurgeService $service) {
    $this->service = $service;
  }

  /**
   * Get the number of expected HTTP requests per single queue item.
   *
   * This number plays an essential role in how Acquia Purge throttles itself,
   * as the higher this number gets, the more work it has to do to process a
   * single queue item. The 'safety first' principle thus dictates that the
   * lower this factor gets, the more items that are processed per queue batch.
   *
   * @return int
   *   The number of expected HTTP requests per single queue item.
   */
  public function httpRequestsFactor() {
    if (is_null($this->httpRequestsFactor)) {
      $domains = count($this->service->hostingInfo()->getDomains());
      $schemes = count($this->service->hostingInfo()->getSchemes());

      // Retrieve the total number of loaded executors, but filter out the
      // bundled page cache executor as it doesn't make any HTTP requests.
      $executors = count(
        array_filter(
          $this->service->executorIds(),
          function($v) {
            return $v !== 'AcquiaPurgeExecutorPageCache';
          }
        )
      );

      // Weigh the role that load balancers play into the equation. Before AP
      // version 7.x-1.4, a standard setup with 2 balancers invalidating just
      // one domain on HTTP, would (logically) get a factor 2X. However, these
      // servers are on the local network, extremely fast and barely ever take
      // longer than 0.2 seconds while we pessimistically assume 2s here. This
      // is why clients with 2 balancers, will now get a factor 1X while odd
      // setups with 3 or higher, are punished (1X, 1X, 3X, 4X) and encouraged
      // to normalize to a standard configuration.
      $balancers = count($this->service->hostingInfo()->getBalancerAddresses());
      if ($balancers == 2) {
        $balancers = 1;
      }

      // Calculate the HTTP request factor as follows:
      $this->httpRequestsFactor =
        $executors    // Most executors will make requests per scheme + domain.
        * $schemes    // HTTP paths differ from HTTPS, thus separate requests.
        * $domains    // URLs get hashed with domains, thus separate requests.
        * $balancers; // Each balancer, holds copies of the same URL.

      // Outside of Acquia Cloud, the factor could become 0. To prevent division
      // by zero, we make it 1. Not a big deal, as the module shuts down.
      if ($this->httpRequestsFactor === 0) {
        $this->httpRequestsFactor = 1;
      }
    }
    return $this->httpRequestsFactor;
  }

  /**
   * Get the number of HTTP requests that executors are allowed to make.
   *
   * @return int
   *   The number of HTTP requests that executors are allowed to make.
   */
  public function httpRequestsLimit() {
    if (is_null($this->httpRequestsLimit)) {

      // For Drush/CLI environments, adopt the overall HTTP requests limit.
      $this->httpRequestsLimit = self::HTTP_REQUESTS_LIMIT;

      // However, once PHP's max_execution_time isn't zero, we'll take 80% of
      // the available seconds and divide that through the HTTP request timeout
      // that a single HTTP request can maximally take. Because of the fact that
      // AcquiaPurgeExecutorBase::requestsExecute() performs parallel HTTP
      // request execution, we multiply by that.
      $max_exec_time = (int) ini_get('max_execution_time');
      if ($max_exec_time != 0) {
        $max_exec_time = intval(ceil(0.8 * $max_exec_time));
        $this->httpRequestsLimit =
          self::HTTP_PARALLEL_REQUESTS * (
            $max_exec_time / self::HTTP_REQUEST_TIMEOUT
          );

        // When this value exceeds the total limit, take the limit instead.
        if ($this->httpRequestsLimit > self::HTTP_REQUESTS_LIMIT) {
          $this->httpRequestsLimit = self::HTTP_REQUESTS_LIMIT;
        }
      }
    }
    return $this->httpRequestsLimit;
  }

  /**
   * Get the number of queue items that can be processed during runtime.
   *
   * @return int
   *   The number of queue items that can be processed during runtime.
   */
  public function queueClaimsLimit() {
    if (is_null($this->queueClaimsLimit)) {
      $requests_per_item = $this->httpRequestsFactor();
      $max_requests = $this->httpRequestsLimit();

      // Divide the HTTP request limit through the number of HTTP requests
      // expected to be made for a single queue item (for a rough indication).
      $this->queueClaimsLimit = intval($max_requests / $requests_per_item);

      // Some tragically configured environments - usually those picking up a
      // lot of domain names - will dive below 1. Set a minimum for these cases.
      if ($this->queueClaimsLimit < 1) {
        $this->queueClaimsLimit = 1;
      }

      // Don't exceed the overall limit of queue claims.
      if ($this->queueClaimsLimit > self::QUEUE_CLAIMS_LIMIT) {
        $this->queueClaimsLimit = self::QUEUE_CLAIMS_LIMIT;
      }
    }
    return $this->queueClaimsLimit;
  }

  /**
   * Subtract processed queue claims from the claim limit.
   *
   * @param int $processed_items
   *   Number of queue claims just processed by AcquiaPurgeService::process().
   */
  public function queueClaimsSubtract($processed_items) {
    $this->queueClaimsLimit();
    if ($this->queueClaimsLimit) {
      $this->queueClaimsLimit - intval($processed_items);
    }
  }

}
