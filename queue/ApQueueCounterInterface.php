<?php

/**
 * @file
 * Contains ApQueueCounterInterface.
 */

/**
 * Describes a object, providing access to counters (int) kept in state storage.
 *
 * @see _acquia_purge_state_get
 * @see _acquia_purge_state_set
 */
interface ApQueueCounterInterface {

  /**
   * Construct the counter object.
   *
   * @param int $state_key
   *   The key with which the counter is stored in state storage.
   */
  public function __construct($state_key);

  /**
   * Decrease the counter.
   *
   * @param int $amount
   *   Numeric amount to decrease the counter with.
   */
  public function decrease($amount = 1);

  /**
   * Increase the counter.
   *
   * @param int $amount
   *   Numeric amount to increase the counter with.
   */
  public function increase($amount = 1);

  /**
   * Get the current counter value.
   *
   * @return int
   */
  public function get();

  /**
   * Set the counter to the given value.
   *
   * @param int $value
   *   Numeric value to set the counter to.
   */
  public function set($value);

}
