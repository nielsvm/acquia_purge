<?php

/**
 * @file
 * Contains ApProcessorInterface.
 */

/**
 * Describes a processor that processes items from the queue.
 */
interface ApProcessorInterface {

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
