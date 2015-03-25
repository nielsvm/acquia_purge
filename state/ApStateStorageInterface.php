<?php

/**
 * @file
 * Contains ApStateStorageInterface.
 */

/**
 * Describes a state storage object that maintains ApStateItem objects.
 */
interface ApStateStorageInterface {

  /**
   * Commit the state data to its persistent storage location.
   */
  public function commit();

  /**
   * Retrieve the object named $key from state storage.
   *
   * @param int $key
   *   The key with which the object is stored in state storage.
   * @param mixed|null $default
   *   (optional) The default value to use if the entry doesn't yet exist.
   *
   * @return ApStateItemInterface
   */
  public function get($key, $default = NULL);

  /**
   * Store the state item in state item storage.
   *
   * @param ApStateItemInterface $item
   *   The ApStateItemInterface object to store.
   */
  public function set(ApStateItemInterface $item);

  /**
   * Wipe all state data.
   */
  public function wipe();

}