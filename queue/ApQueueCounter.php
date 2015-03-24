<?php

/**
 * @file
 * Contains ApQueueCounter.
 */

/**
 * Integer counter kept in Acquia Purge's state storage mechanism.
 *
 * @see _acquia_purge_state_get
 * @see _acquia_purge_state_set
 */
class ApQueueCounter implements ApQueueCounterInterface {

  /**
   * Initial counter value.
   *
   * @var int
   */
  const INITIAL = 0;

  /**
   * The name of the state storage key for the counter.
   *
   * @var string
   */
  protected $state_key;

  /**
   * The current value of the counter.
   *
   * @var int
   */
  protected $current;

  /**
   * {@inheritdoc}
   */
  public function __construct($state_key) {
    $this->state_key = 'q' . $state_key;
    $this->current = _acquia_purge_state_get($this->state_key, SELF::INITIAL);
  }

  /**
   * {@inheritdoc}
   */
  public function decrease($amount = 1) {
    $this->current = $this->current - intval($amount);
    _acquia_purge_state_set($this->state_key, $this->current);
  }

  /**
   * {@inheritdoc}
   */
  public function increase($amount = 1) {
    $this->current = $this->current + intval($amount);
    _acquia_purge_state_set($this->state_key, $this->current);
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this->current;
  }

  /**
   * {@inheritdoc}
   */
  public function set($value) {
    $this->current = intval($value);
    _acquia_purge_state_set($this->state_key, $this->current);
  }

}
