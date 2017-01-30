<?php

/**
 * Describes an invalidation object.
 *
 * Invalidations are small value objects that describe an individual path from
 * the Acquia Purge queue, to be invalidated for the given scheme and domain
 * name. Executors are responsible for calling ::setStatus(), so that at the end
 * of processing, ::getStatus() evaluates whether the invalidation succeeded
 * across all executor engines. This means that a failed CloudEdge purge, would
 * render the entire path as failed so that it goes back into the queue.
 */
interface AcquiaPurgeInvalidationInterface extends AcquiaPurgeQueueStatusInterface {

  /**
   * Constructs an invalidation queue item object.
   *
   * @param string $scheme
   *   The requested scheme to be invalidated: 'http' or 'https'.
   * @param string $domain
   *   The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   * @param string $base_path
   *   Drupal's base path (or the one Acquia Purge is told to clear).
   * @param AcquiaPurgeQueueItemInterface $queue_item
   *   The queue item to which this invalidation is associated.
   */
  public function __construct($scheme, $domain, $base_path, AcquiaPurgeQueueItemInterface $queue_item);

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
   * Get the fully qualified URI.
   *
   * @return string
   *   Full URL, e.g. https://domain.com/basepath/user/1?d=foo.
   */
  public function getUri();

  /**
   * Detect if the invalidation has a '*' character in it.
   *
   * @return bool
   *   Returns TRUE when the expression contains a wildcard, FALSE otherwise.
   */
  public function hasWildcard();

}
