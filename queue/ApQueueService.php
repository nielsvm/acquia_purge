<?php

/**
 * @file
 * Contains ApQueueService.
 */

/**
 * The Acquia Purge queue service.
 */
class ApQueueService {

  /**
   * The module path.
   *
   * @var string
   */
  protected $module_path;

  /**
   * Deduplication lists.
   *
   * @var array[]
   */
  protected $deduplicate_lists = array();

  /**
   * Purged URLs for UI visualization.
   *
   * @var string[]
   */
  protected $history = array();

  /**
   * The loaded queue backend.
   *
   * @var ApQueueInterface
   */
  protected $queue = NULL;

  /**
   * The loaded state storage backend.
   *
   * @var ApStateStorageInterface
   */
  protected $state = NULL;

  /**
   * Construct ApQueueService.
   */
  public function __construct() {
    $this->module_path = drupal_get_path('module', 'acquia_purge');
  }

  /**
   * Queue a single path and trigger the UI processor (if not using cron).
   *
   * @param string $path
   *   The Drupal path (for example: '<front>', 'user/1' or a alias).
   *
   * @return array
   *   Associative array with the keys 'running', 'total', 'remaining',
   *   'good', 'bad', 'percent' and 'purgehistory'.
   */
  public function addPath($path) {
    $path = _acquia_purge_input_clean($path);

    // Queue the item when it is unique and trigger UI purging.
    if (!$this->deduplicate($path)) {
      if ($this->queue()->createItem(array($path))) {
        _acquia_purge_ajaxprocessor_trigger();
      }
    }

    return $this->stats();
  }

  /**
   * Queue several paths and trigger the UI processor (if not using cron).
   *
   * @param string[] $paths
   *   Array with Drupal paths (for example: '<front>', 'user/1' or a alias).
   *
   * @return array
   *   Associative array with the keys 'running', 'total', 'remaining',
   *   'good', 'bad', 'percent' and 'purgehistory'.
   */
  public function addPaths(array $paths) {
    $items = array();

    // Clean the paths, skip duplicates and build the item array.
    foreach ($paths as $path) {
      $path = _acquia_purge_input_clean($path);
      if (!$this->deduplicate($path)) {
        $items[] = array($path);
      }
    }

    // Queue the items and trigger the UI to start processing for this user.
    if ($this->queue()->createItemMultiple($items)) {
      _acquia_purge_ajaxprocessor_trigger();
    }

    return $this->stats();
  }

  /**
   * Empty the queue and reset all state data.
   */
  public function clear() {
    $this->lock(NULL);
    $this->queue()->deleteQueue();
    $this->state()->wipe();
  }

  /**
   * Prevent duplicate path queuing and purging.
   *
   * Our queue is database backed and if we would query every path before it
   * ends up in the queue, the cost would become too expensive. This helper
   * however, maintains breadcrumb lists of the paths it was given and returns
   * FALSE for new items and TRUE for old items. Items are theoretically kept
   * till the queue is emptied.
   *
   * If the site has 'acquia_purge_memcache' set to TRUE, the implementation
   * will use the state storage mechanism in addition to the static variables,
   * which means that data will persist between requests. With the file-based
   * state storage, this would result in massive IO activity so less accurate
   * deduplication is acceptable.
   *
   * @warning
   *   Duplicated paths can still end up in the queue, especially when not using
   *   the 'acquia_purge_memcache' setting.
   *
   * @param string $path
   *   The Drupal path (for example: '<front>', 'user/1' or a alias).
   * @param string $list
   *   (optional) Two breadcrumb lists are kept, 'queued' for preventative
   *   deduplication and 'purged' for keeping a post-purge track record.
   * @param int $l
   *   (optional) The $l parameter stands for 'limit' and represents the
   *   amount of items in a list to be crossed before it gets emptied.
   *
   * @return true|false
   *   TRUE when the path is in the given list, FALSE when not.
   */
  public function deduplicate($path, $list = 'queued', $l = 500) {
    $memcached_backed_storage = _acquia_purge_are_we_using_memcached();

    // And then each $list gets its own subsection.
    if (!isset($this->deduplicate_lists[$list])) {
      $this->deduplicate_lists[$list] = array();
      if ($memcached_backed_storage) {
        $this->deduplicate_lists[$list] = $this->state()
          ->get($list, array())
          ->get();
      }
    }

    // Check if it exists before list rotation, then add missing items.
    $exists = in_array($path, $this->deduplicate_lists[$list]);
    if (count($this->deduplicate_lists[$list]) >= $l) {
      $this->deduplicate_lists[$list] = array();
    }
    if (!$exists) {
      $this->deduplicate_lists[$list][] = $path;
      if ($memcached_backed_storage) {
        $this->state()->get($list, array())->set($this->deduplicate_lists[$list]);
      }
    }

    return $exists;
  }

  /**
   * Maintains a runtime list of purged URLs for UI visualization.
   *
   * @param string $url
   *   (optional) When passed in the given URL will be added to the history log.
   *
   * @return string[]
   *   The full non-associative array with URLs kept in memory.
   */
  public function history($url = NULL) {
    if (!is_null($url)) {
      $this->history[] = $url;
    }
    return $this->history;
  }

  /**
   * Acquire a lock and get permission to process the queue.
   *
   * @param bool $acquire
   *   (optional) TRUE to acquire a lock, NULL to release it.
   *
   * @return bool|null
   *   TRUE when the lock is acquired.
   *   FALSE if it is still locked
   *   NULL when $acquire isn't TRUE.
   *
   * @see lock_acquire()
   */
  public function lock($acquire = TRUE) {
    if ($acquire === NULL) {
      $this->locked()->set(FALSE);
      $this->state()->commit();
      lock_release('_acquia_purge_queue_lock');
      return;
    }
    if (lock_acquire('_acquia_purge_queue_lock', 60)) {
      $this->locked()->set(TRUE);
      $this->state()->commit();
      return TRUE;
    }
    else {
      $this->locked()->set(FALSE);
      $this->state()->commit();
      return FALSE;
    }
  }

  /**
   * Retrieve the 'locked' state item from state storage.
   *
   * @return ApStateItemInterface
   */
  public function locked() {
    return $this->state()->get('locked', FALSE);
  }

  /**
   * Retrieve the 'logged_errors' state item from state storage.
   *
   * @return ApStateItemInterface
   */
  public function loggedErrors() {
    return $this->state()->get('logged_errors', array());
  }

  /**
   * Process as many items from the queue as the runtime capacity allows.
   *
   * @param string $callback
   *   (optional) A PHP callable that processes one queue item, which will get
   *   called with call_user_func_array(). The callback MUST return TRUE on
   *   success and FALSE when it failed so queue items can get released/deleted.
   *
   *   The $callback is committed to processing the item. Crashes during the
   *   callback's execution, will result in a claimed queue item not getting
   *   processed until it expired.
   *
   * @return bool
   *   Returns TRUE when it processed items, FALSE when the capacity limit has
   *   been reached or when the queue is empty and there's nothing left to do.
   */
  function process($callback = '_acquia_purge_purge') {

    // Do not even attempt to process when the total counter is zero.
    if ($this->queue()->counter('qtotal')->get() === 0) {
      return FALSE;
    }

    // How much can we safely process during this request?
    $maxitems = _acquia_purge_get_capacity();
    if ($maxitems < 1) {
      return FALSE;
    }

    // Claim a number of items we can maximally process during request lifetime.
    if (!($claims = $this->queue()->claimItemMultiple($maxitems))) {
      $this->state()->wipe();
      return FALSE;
    }

    // Process the claims and let the queue delete/release them.
    $deletes = $releases = array();
    foreach ($claims as $claim) {
      if ($this->deduplicate($claim->data[0], 'purged')) {
        $deletes[] = $claim;
        continue;
      }
      if (call_user_func_array($callback, $claim->data)) {
        $this->deduplicate($claim->data[0], 'purged');
        $deletes[] = $claim;
      }
      else {
        $releases[] = $claim;
      }
    }
    $this->queue()->deleteItemMultiple($deletes);
    $this->queue()->releaseItemMultiple($releases);

    // Adjust the remaining capacity downwards for future ::process() calls.
    _acquia_purge_get_capacity(count($deletes) + count($releases));

    // When the bottom of the queue has been reached, reset all state data.
    if ($this->queue()->numberOfItems() === 0) {
      $this->state()->wipe();
    }

    return TRUE;
  }

  /**
   * Initialize the queue backend object.
   *
   * @return ApQueueInterface
   */
  protected function queueInitialize() {
    if (is_null($this->queue)) {
      $state = $this->state();

      // Require all code if the autoloader has not yet done so already.
      require_once($this->module_path . '/queue/ApQueueCounterInterface.php');
      require_once($this->module_path . '/queue/ApQueueCounter.php');
      require_once($this->module_path . '/queue/ApQueueInterface.php');
      require_once($this->module_path . '/queue/backend/ApEfficientQueue.php');

      // Load the configured smart or normal backend.
      if (_acquia_purge_variable('acquia_purge_smartqueue')) {
        require_once($this->module_path . '/queue/backend/ApSmartQueue.php');
        $this->queue = new ApSmartQueue($state);
      }
      else {
        $this->queue = new ApEfficientQueue($state);
      }
    }
    return $this->queue;
  }

  /**
   * Retrieve the loaded queue backend object.
   *
   * @return ApQueueInterface
   */
  public function queue() {
    $this->queueInitialize();
    return $this->queue;
  }

  /**
   * Retrieve the state storage object.
   *
   * @return ApStateStorageInterface
   */
  public function state() {

    // Initialize the state storage backend.
    if (is_null($this->state)) {
      require_once($this->module_path . '/state/ApStateStorageInterface.php');
      require_once($this->module_path . '/state/ApStateItemInterface.php');
      require_once($this->module_path . '/state/ApStateItem.php');
      if (_acquia_purge_are_we_using_memcached()) {
        require_once($this->module_path
          . '/state/backend/ApMemcachedStateStorage.php');
        $this->state = new ApMemcachedStateStorage(
          ACQUIA_PURGE_STATE_MEMKEY,
          ACQUIA_PURGE_STATE_MEMBIN
        );
      }
      else {
        require_once($this->module_path
          . '/state/backend/ApDiskStateStorage.php');
        $this->state = new ApDiskStateStorage(ACQUIA_PURGE_STATE_FILE);
      }
    }

    return $this->state;
  }

  /**
   * Retrieve progress statistics.
   *
   * @param string $key
   *   (optional) The requested statistics key to return.
   *
   * @return array
   *   Associative array with the keys 'running', 'total', 'remaining',
   *   'good', 'bad', 'percent' and 'purgehistory'.
   */
  public function stats($key = NULL) {
    $info = array(
      'purgehistory' => $this->history(),
      'locked' => $this->locked()->get(),
      'total' => $this->queue()->counter('qtotal')->get(),
      'good' => $this->queue()->counter('qgood')->get(),
      'bad' => $this->queue()->counter('qbad')->get(),
      'remaining' => 0,
      'percent' => 100,
      'running' => FALSE,
    );

    // Calculate the percentages when the queue doesn't seem to be empty.
    if ($info['total'] !== 0) {
      $info['running'] = TRUE;
      $info['remaining'] = $info['total'] - $info['good'];
      $info['percent'] = ($info['remaining'] / $info['total']) * 100;
      $info['percent'] = (int) (100 - floor($info['percent']));
    }

    return is_null($key) ? $info : $info[$key];
  }

  /**
   * Retrieve the 'uiusers' state item from state storage.
   *
   * @return ApStateItemInterface
   */
  public function uiUsers() {
    return $this->state()->get('uiusers', array());
  }

}
