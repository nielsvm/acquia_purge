<?php

/**
 * @file
 * Contains ApStateItemInterface.
 */

/**
 * Describes a single state item kept in state storage.
 */
interface ApStateItemInterface {

  /**
   * Construct a state item object.
   *
   * @param ApStateStorageInterface $storage
   *   The state storage in which the item has been stored.
   * @param int $key
   *   The key with which the object is stored in state storage.
   * @param mixed $value
   *   The value of the state item.
   */
  public function __construct(ApStateStorageInterface $storage, $key, $value);

  /**
   * Get the item value.
   *
   * @return mixed
   */
  public function get();

  /**
   * Get the item key.
   *
   * @return string
   */
  public function getKey();

  /**
   * Store the state item in state item storage.
   *
   * @param mixed $value
   *   The new value.
   */
  public function set($value);

}
