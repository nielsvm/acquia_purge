<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorInterface.
 */

/**
 * Describes a executor, which executes a set of path purges.
 */
interface AcquiaPurgeExecutorInterface {

  /**
   * Determine if the processor considers itself enabled.
   */
  public static function isEnabled();

  /**
   * Subscribe to the events this processor requires.
   *
   * @return string[]
   *   Non-associative array of event names.
   */
  public function getSubscribedEvents();

}
