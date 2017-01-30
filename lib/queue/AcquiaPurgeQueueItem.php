<?php

/**
 * Provides a queue item object.
 *
 * On the outside, these queue objects are identical in behavior to Drupal's
 * standard DrupalReliableQueueInterface::claimItem() objects and allow read
 * access to its 'item_id', 'created' and 'data' properties.
 *
 * On top of that however, these objects are capable of letting executor plugins
 * set statuses on them through the ::setStatus() method on the invalidation
 * objects created by ::getInvalidation(). With this, we can easily evaluate the
 * overall outcome at the end and claim or delete it from the queue.
 */
class AcquiaPurgeQueueItem implements AcquiaPurgeQueueItemInterface {
  use AcquiaPurgeQueueStatusTrait;

  /**
   * The invalidation class to instantiate invalidation objects from.
   *
   * @var string
   */
  protected $class_invalidation;

  /**
   * Timestamp when the item was put into the queue.
   *
   * @var int
   * @see AcquiaPurgeQueueInterface::createItem
   * @see AcquiaPurgeQueueInterface::claimItem
   */
  protected $created;

  /**
   * Purge specific data to be associated with the new task in the queue.
   *
   * @var mixed
   * @see AcquiaPurgeQueueInterface::createItem
   * @see AcquiaPurgeQueueInterface::claimItem
   */
  protected $data;

  /**
   * Timestamp when the item expires from the queue.
   *
   * @var int
   * @see AcquiaPurgeQueueInterface::createItem
   * @see AcquiaPurgeQueueInterface::claimItem
   */
  protected $expire;

  /**
   * The unique ID from AcquiaPurgeQueueInterface::createItem().
   *
   * @var string|int
   * @see AcquiaPurgeQueueInterface::createItem
   * @see AcquiaPurgeQueueInterface::claimItem
   */
  protected $item_id;

  /**
   * Describes the accessible properties and if they're RO (FALSE) or RW (TRUE).
   *
   * @var bool[string]
   */
  protected $properties = [
    'item_id' => TRUE,
    'created' => TRUE,
    'data' => FALSE,
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct($created, $data, $expire, $item_id) {
    $this->created = $created;
    $this->item_id = $item_id;
    $this->expire = $expire;
    $this->data = $data;
    $this->class_invalidation = _acquia_purge_load(
      array(
        '_acquia_purge_invalidation_interface',
        '_acquia_purge_invalidation'
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    if (!isset($this->properties[$name])) {
      throw new RuntimeException("The property '$name' does not exist.");
    }
    return $this->$name;
  }

  /**
   * {@inheritdoc}
   */
  public function __set($name, $value) {
    if (!isset($this->properties[$name])) {
      throw new RuntimeException("The property '$name' does not exist.");
    }
    if (!$this->properties[$name]) {
      throw new RuntimeException("The property '$name' is read-only.");
    }
    $this->$name = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getInvalidation($scheme, $domain, $base_path) {
    return new $this->class_invalidation($scheme, $domain, $base_path, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->data[0];
  }

}
