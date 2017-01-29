<?php

/**
 * Describes an invalidation object.
 *
 * Invalidations are small value objects that describe an individual path from
 * the Acquia Purge queue, to be invalidated for the given scheme and domain
 * name. Executors are responsible for calling ::setState(), so that at the end
 * of processing, ::getState() evaluates whether the invalidation succeeded
 * across all executor engines. This means that a failed CloudEdge purge, would
 * render the entire path as failed so that it goes back into the queue.
 */
interface AcquiaPurgeInvalidationInterface {

  /**
   * Invalidation is new and no processing has been attempted on it yet.
   *
   * @var int
   */
  const FRESH = 0;

  /**
   * The invalidation succeeded.
   *
   * @var int
   */
  const SUCCEEDED = 1;

  /**
   * The invalidation failed and will be offered again later.
   *
   * @var int
   */
  const FAILED = 2;

  /**
   * Constructs an invalidation value object.
   *
   * @param string $scheme
   *   The requested scheme to be invalidated: 'http' or 'https'.
   * @param string $domain
   *   The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   * @param string $base_path
   *   Drupal's base path (or the one Acquia Purge is told to clear).
   * @param object $queue_item
   *   Queue item object as defined in AcquiaPurgeQueueInterface::claimItem(),
   *   with at least the following properties:
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   */
  public function __construct($scheme, $domain, $base_path, $queue_item);

  /**
   * Get the HTTP scheme.
   *
   * @param bool $fqn
   *   Pass TRUE for 'http://' or FALSE for 'http'.
   *
   * @return string
   *   The requested scheme to be invalidated: 'http' or 'https'.
   */
  public function getScheme($fqn = FALSE);

  /**
   * Get the HTTP domain name.
   *
   * @return string
   *   The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   */
  public function getDomain();

  /**
   * Get the HTTP path.
   *
   * @return string
   *   The path to wipe, e.g. '/basepath/user/1?d=foo' or '/basepath/news/*'.
   */
  public function getPath();

  /**
   * Get the queue item.
   *
   * @return object
   *   Queue item object as defined in AcquiaPurgeQueueInterface::claimItem(),
   *   with at least the following properties:
   *     - data: the same as what what passed into createItem().
   *     - item_id: the unique ID returned from createItem().
   *     - created: timestamp when the item was put into the queue.
   */
  public function getQueueItem();

  /**
   * Get the fully qualified URI.
   *
   * @return string
   *   Full URL, e.g. https://domain.com/basepath/user/1?d=foo.
   */
  public function getUri();

  /**
   * Get the current or general state of the invalidation.
   *
   * New, freshly claimed invalidations area always in the NULL context. This
   * context is normal when the invalidation object doesn't yet float from
   * executor to executor, and is called the "general context". calling
   * ::getState() will then evaluate the "global" state for the invalidation.
   *
   * However, the behaviors of ::getState() and ::setState() change after a call
   * to ::setStateContext(). From this point on, both will respectively retrieve
   * and store the state *specific* to that executor context. Context switching
   * is done by AcquiaPurgeService::process() and therefore simple executor
   * implementations don't require thorough understanding of this concept.
   *
   * @throws \LogicException
   *   Thrown state are stored that should not have been stored, as is not
   *   never supposed to happen catching this exception is not recommended.
   *
   * @return int
   *   Either SELF::FRESH, SELF::FAILED or SELF::SUCCEEDED.
   */
  public function getState();

  /**
   * Get the current state as string.
   *
   * @return string
   *   A capitalized string, either "FRESH", "FAILED" or "SUCCEEDED".
   */
  public function getStateString();

  /**
   * Detect if the invalidation has a '*' character in it.
   *
   * @return bool
   *   Returns TRUE when the expression contains a wildcard, FALSE otherwise.
   */
  public function hasWildcard();

  /**
   * Set the state of the invalidation.
   *
   * Setting state on invalidation objects is the responsibility of executors,
   * as only executors decide what succeeded and what failed. For this reason a
   * call to ::setStateContext() before ::setState() is required at all times.
   *
   * @param int $state
   *   One of the following states:
   *   - SELF::SUCCEEDED
   *   - SELF::FAILED
   *
   * @throws \RuntimeException
   *   Thrown when the $state parameter doesn't match any of the constants
   *   defined in AcquiaPurgeInvalidationInterface.
   * @throws \LogicException
   *   Thrown when the state is being set in general context.
   *
   * @return void
   */
  public function setState($state);

  /**
   * Set (or reset) state context to the executor instance next in line.
   *
   * New, freshly claimed invalidations area always in the NULL context. This
   * context is normal when the invalidation object doesn't yet float from
   * executor to executor, and is called the "general context". calling
   * ::getState() will then evaluate the "global" state for the invalidation.
   *
   * However, the behaviors of ::getState() and ::setState() change after a call
   * to ::setStateContext(). From this point on, both will respectively retrieve
   * and store the state *specific* to that executor context. Context switching
   * is done by AcquiaPurgeService::process() and therefore simple executor
   * implementations don't require thorough understanding of this concept.
   *
   * @param string|null $executor_instance_id
   *   The instance ID of the executor that is about to process the object, or
   *   NULL when no longer any executors are processing it. NULL is the default.
   *
   * @throws \LogicException
   *   Thrown when the given parameter is empty, not a string or NULL.
   * @throws \LogicException
   *   Thrown when the last set state was not any of:
   *   - AcquiaPurgeInvalidationInterface::SUCCEEDED
   *   - AcquiaPurgeInvalidationInterface::FAILED
   *
   * @see AcquiaPurgeInvalidationInterface::setState()
   *
   * @return void
   */
  public function setStateContext($executor_instance_id);

}
