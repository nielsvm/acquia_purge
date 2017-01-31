<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorInterface.
 */

/**
 * Describes an executor, which is responsible for taking a set of invalidation
 * objects and wiping these paths/URLs from an external cache.
 */
interface AcquiaPurgeExecutorInterface {

  /**
   * Construct an executor object.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service.
   */
  public function __construct(AcquiaPurgeService $service);

  /**
   * Get a unique identifier for this executor.
   *
   * @return string
   */
  public function getId();

  /**
   * Instantiate a new request object.
   *
   * @param string $uri
   *   The URL to connect to, e.g.: http://domain.com/path/a/b/c
   *
   * @return AcquiaPurgeExecutorRequestInterface
   */
  public function getRequest($uri = NULL);

  /**
   * Invalidate one or multiple paths from an external layer.
   *
   * This method is responsible for clearing all the given invalidation objects
   * from the external cache layer this executor covers. Executors decide /how/
   * they clear something, as long as they correctly call ::setStatusSucceeded()
   * or ::setStatusFailed() on each processed object.
   *
   * @param AcquiaPurgeInvalidationInterface[] $invalidations
   *   Unassociative list of AcquiaPurgeInvalidationInterface-compliant objects
   *   that contain the necessary info in them. You may likely need several of
   *   the following methods on the invalidation object:
   *     - ::getScheme(): e.g.: 'https' or 'https://' when passing TRUE.
   *     - ::getDomain(): e.g.: 'site.com'
   *     - ::getPath(): e.g.: '/basepath/products/electrical/*'
   *     - ::getUri(): e.g.: 'https://site.com/basepath/products/electrical/*'
   *     - ::hasWildcard(): call this to find out if there's a asterisk ('*').
   *     - ::setStatusFailed(): call this when clearing this item failed.
   *     - ::setStatusSucceeded(): call this when clearing this item succeeded.
   */
  public function invalidate($invalidations);

  /**
   * Determine if the executor is enabled or not.
   */
  public static function isEnabled();

  /**
   * Execute a series of HTTP requests efficiently through cURL's multi handler.
   *
   * @param AcquiaPurgeExecutorRequestInterface[] $requests
   *   Unassociative list of request objects created by ::getRequest(). The
   *   properties 'scheme', 'method', 'uri' are used for executing the request.
   *   The properties 'result', 'error_curl', 'response_code' and 'error_debug'
   *   are updated during execution.
   */
  public function requestsExecute($requests);

  /**
   * Log a series of requests according to their 'result' properties.
   *
   * @param AcquiaPurgeExecutorRequestInterface[] $requests
   *   Unassociative list of ::requestsExecute() processed requests.
   * @param string $consequence
   *   Appendable message determing what happens because of a failed request.
   */
  public function requestsLog($requests, $consequence = 'goes back to queue');

}
