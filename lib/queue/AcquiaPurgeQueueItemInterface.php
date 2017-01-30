<?php

/**
 * Describes a queue item object.
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
interface AcquiaPurgeQueueItemInterface extends AcquiaPurgeQueueStatusInterface {

  /**
   * Constructs an queue item object.
   *
   * @param int $created
   *   Timestamp when the item was put into the queue.
   * @param mixed $data
   *   Purge specific data to be associated with the new task in the queue.
   * @param int $expire
   *   Timestamp when the item expires from the queue.
   * @param string|int $item_id
   *   The unique ID from AcquiaPurgeQueueInterface::createItem()
   */
  public function __construct($created, $data, $expire, $item_id);

  /**
   * Retrieve a property.
   *
   * @param string $name
   *   The name of the property, can be 'item_id', 'created' or 'data'.
   *
   * @throws \RuntimeException
   *   Thrown when the requested property isn't 'item_id', 'created' or 'data'.
   *
   * @see http://php.net/manual/en/language.oop5.overloading.php#object.get
   *
   * @return mixed
   */
  public function __get($name);

  /**
   * Set a writable property.
   *
   * @param string $name
   *   The name of the property, can be 'item_id' or 'created'.
   * @param mixed $value
   *   The value of the property you want to set.
   *
   * @throws \RuntimeException
   *   Thrown when the requested property isn't 'item_id' or 'created'.
   *
   * @see http://php.net/manual/en/language.oop5.overloading.php#object.set
   */
  public function __set($name, $value);

  /**
   * Create a invalidation object for the given scheme, domain and base path.
   *
   * @param string $scheme
   *   The requested scheme to be invalidated: 'http' or 'https'.
   * @param string $domain
   *   The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   * @param string $base_path
   *   Drupal's base path (or the one Acquia Purge is told to clear).
   *
   * @return AcquiaPurgeInvalidationInterface
   */
  public function getInvalidation($scheme, $domain, $base_path);

}
