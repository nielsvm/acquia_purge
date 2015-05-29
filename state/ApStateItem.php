<?php

/**
 * @file
 * Contains ApStateItem.
 */

/**
 * Provides a single state item kept in state storage.
 */
class ApStateItem implements ApStateItemInterface {

  /**
   * The state storage in which the item has been stored.
   *
   * @var ApStateStorageInterface
   */
  protected $storage;

  /**
   * The key with which the object is stored in state storage.
   *
   * @var string
   */
  protected $key;

  /**
   * The value of the state item.
   *
   * @var mixed
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($storage, $key, $value) {
    $this->storage = $storage;
    $this->value = $value;
    $this->key = $key;
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    $this->value = $value;
    $this->storage->set($this);
  }

}
