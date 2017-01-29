<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorInterface.
 */

/**
 * Describes an executor, which executes a set of path purges.
 */
interface AcquiaPurgeExecutorInterface {

  /**
  * Determine if the executor is enabled or not.
  */
  public static function isEnabled();

  /**
   * Construct a executor object.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service instance.
   */
  public function __construct(AcquiaPurgeService $service);

  /**
   * Claims multiple items from the queue for processing.
   *
   * @param string $scheme
   *   The requested scheme to be invalidated: 'http' or 'https'.
   * @param string $domain
   *   The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   * @param string $path
   *   The path to wipe, e.g. 'user/1?destination=foo' or 'news/*'.
   *
   * @return array
   *   On success we return a non-associative array with item objects. When the
   *   queue has no items that can be claimed, this doesn't return FALSE as
   *   claimItem() does, but an empty array instead.
   *
   *   If claims return, the objects have at least these properties:
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   */
  public function getRequests($scheme, $domain, $path);

  /**
   * Claims multiple items from the queue for processing.
   *
   * @param int $claims
   *   Determines how many claims at once should be claimed from the queue. When
   *   the queue is unable to return as many items as requested it will return
   *   as much items as it can.
   * @param int $lease_time
   *   How long the processing is expected to take in seconds, defaults to an
   *   hour. After this lease expires, the item will be reset and another
   *   consumer can claim the item. For idempotent tasks (which can be run
   *   multiple times without side effects), shorter lease times would result
   *   in lower latency in case a consumer fails. For tasks that should not be
   *   run more than once (non-idempotent), a larger lease time will make it
   *   more rare for a given task to run multiple times in cases of failure,
   *   at the cost of higher latency.
   *
   * @return array
   *   On success we return a non-associative array with item objects. When the
   *   queue has no items that can be claimed, this doesn't return FALSE as
   *   claimItem() does, but an empty array instead.
   *
   *   If claims return, the objects have at least these properties:
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   */
  public function evaluate($request);


}
