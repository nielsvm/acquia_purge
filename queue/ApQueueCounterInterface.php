<?php

/**
 * @file
 * Contains ApQueueCounterInterface.
 */

/**
 * Describes a object, providing access to counters (int) kept in state storage.
 */
interface ApQueueCounterInterface {

  /**
   * Construct the counter state item object.
   *
   * @param ApStateItemInterface $item
   *   The state item object that holds the raw counter data.
   */
  public function __construct(ApStateItemInterface $item);

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
