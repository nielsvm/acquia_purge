<?php

/**
 * @file
 * Contains ApDiskStateStorage.
 */

/**
 * File backed state storage.
 */
class ApDiskStateStorage implements ApStateStorageInterface {

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
   * The item payloads as it gets read and written from disk.
   *
   * @var mixed[]
   */
  protected $payload = array();

  /**
   * The raw payload to compare changes against.
   *
   * @var string
   */
  protected $raw = '';

  /**
   * The URI identifier to the file (on disk) to store state data in.
   *
   * @var string
   */
  protected $uri;

  /**
   * Construct ApDiskStateStorage.
   *
   * @param string $uri
   *   The URI identifier to the file (on disk) to store state data in.
   */
  public function __construct($uri) {
    $this->uri = $uri;

    // Load the state data from disk if the file exists.
    if (file_exists($this->uri)) {
      $this->raw = file_get_contents($uri);
      $this->payload = unserialize($this->raw);
      if (is_array($this->payload) && count($this->payload)) {
        foreach ($this->payload as $key => $value) {
          $this->items[$key] = new ApStateItem($this, $key, $value);
        }
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
    $raw_new = serialize($this->payload);
    if ($raw_new !== $this->raw) {
      $this->raw = $raw_new;
      file_put_contents($this->uri, $this->raw);
    }
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
    $this->payload[$item->getKey()] = $item->get();
  }

  /**
   * {@inheritdoc}
   */
  public function wipe() {
    $this->items = array();
    $this->payload = array();
    $this->raw = '';
    if (file_exists($this->uri)) {
      drupal_unlink($this->uri);
    }
  }

}
