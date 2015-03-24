<?php

/**
 * @file
 * Contains ApMemcachedStateStorage.
 */

/**
 * Memcached backed state storage.
 */
class ApMemcachedStateStorage implements ApStateStorageInterface {

  /**
   * Whether ::commit as been registered as shutdown function.
   *
   * @var bool
   */
  protected $commit_registered = FALSE;

  /**
   * Item instances.
   *
   * @var ApStateItemInterface[]
   */
  protected $items = array();

  /**
   * Memcached key used to store our state data in.
   *
   * @var string
   */
  protected $key;

  /**
   * Memcached bin used to store our state data in memcached.
   *
   * @var string
   */
  protected $bin;

  /**
   * The payload buffer which gets synchronized with memcached.
   *
   * @var mixed[]
   */
  protected $buffer = array();

  /**
   * Construct ApMemcachedStateStorage.
   *
   * @param string $key
   *   Memcached key used to store our state data in.
   * @param string $bin
   *   Memcached bin used to store our state data in memcached.
   */
  public function __construct($key, $bin) {
    $this->key = $key;
    $this->bin = $bin;

    // Attempt to retrieve stored state items.
    $data = dmemcache_get($this->key, $this->bin);
    if (is_array($data) && count($data)) {
      $this->buffer = $data;
      foreach ($this->buffer as $key => $value) {
        $this->items[$key] = new ApStateItem($this, $key, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function commit() {
    if (!$this->commit_registered) {
      return;
    }
    else {
      $this->commit_registered = FALSE;
    }
    dmemcache_set($this->key, $this->buffer, 0, $this->bin);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    if (!isset($this->items[$key])) {
      $this->items[$key] = new ApStateItem($this, $key, $default);
    }
    return $this->items[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function set(ApStateItemInterface $item) {
    if (!$this->commit_registered) {
      $this->commit_registered = TRUE;
      drupal_register_shutdown_function(array($this, 'commit'));
    }
    $this->items[$item->getKey()] = $item;
    $this->buffer[$item->getKey()] = $item->get();
  }

  /**
   * {@inheritdoc}
   */
  public function wipe() {
    $this->items = array();
    $this->buffer = array();
    dmemcache_delete($this->memkey, $this->membin);
  }

}
