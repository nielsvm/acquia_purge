<?php

/**
 * @file
 * Contains ApStateCounter.
 */

/**
 * Integer counter kept in Acquia Purge's state storage mechanism.
 */
class ApStateCounter implements ApStateCounterInterface {

  /**
   * The state item that contains our int value.
   *
   * @var ApStateItemInterface
   */
  protected $item;

  /**
   * {@inheritdoc}
   */
  public function __construct(ApStateItemInterface $item) {
    $this->item = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function decrease($amount = 1) {
    $this->item->set(intval($this->item->get()) - intval($amount));
  }

  /**
   * {@inheritdoc}
   */
  public function increase($amount = 1) {
    $this->item->set(intval($this->item->get()) + intval($amount));
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return intval($this->item->get());
  }

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    $this->item->set(intval($value));
  }

}
