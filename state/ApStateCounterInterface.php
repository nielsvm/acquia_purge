<?php

/**
 * @file
 * Contains ApStateCounterInterface.
 */

/**
 * Describes a single counter kept in state storage.
 */
interface ApStateCounterInterface extends ApStateItemInterface {

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

}
