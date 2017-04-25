<?php

/**
 * @file
 * Contains AcquiaPurgeService.
 */

/**
 * The Acquia Purge service.
 *
 * The object from this class is accessed through _acquia_purge_service() and
 * provides access to the underlying queue backend, the modules state API and
 * the loaded processors.
 */
class AcquiaPurgeService {

  /**
   * The module path.
   *
   * @var string
   */
  public $modulePath;

  /**
   * Drupal's base path (or the one Acquia Purge is told to clear).
   *
   * @var string
   */
  private $basePath;

  /**
   * Deduplication lists.
   *
   * @var array[]
   */
  protected $deduplicateLists = array();

  /**
   * The loaded AcquiaPurgeCapacity object.
   *
   * @var AcquiaPurgeCapacity
   */
  protected $capacity = NULL;

  /**
   * The loaded AcquiaPurgeDiagnostics object.
   *
   * @var AcquiaPurgeDiagnostics
   */
  protected $diagnostics = NULL;

  /**
   * The loaded AcquiaPurgeExecutorsService object.
   *
   * @var AcquiaPurgeExecutorsService
   */
  protected $executors = NULL;

  /**
   * Purged URLs for UI visualization.
   *
   * @var string[]
   */
  protected $history = array();

  /**
   * The loaded AcquiaPurgeHostingInfo object.
   *
   * @var AcquiaPurgeHostingInfo
   */
  protected $hostingInfo = NULL;

  /**
   * The loaded AcquiaPurgeOddities object.
   *
   * @var AcquiaPurgeOddities
   */
  protected $oddities = NULL;

  /**
   * The loaded AcquiaPurgeProcessorsService object.
   *
   * @var AcquiaPurgeProcessorsService
   */
  protected $processors = NULL;

  /**
   * The loaded queue backend.
   *
   * @var AcquiaPurgeQueueInterface
   */
  protected $queue = NULL;

  /**
   * The loaded state storage backend.
   *
   * @var AcquiaPurgeStateStorageInterface
   */
  protected $state = NULL;

  /**
   * Construct AcquiaPurgeService.
   */
  public function __construct() {
    $this->modulePath = drupal_get_path('module', 'acquia_purge');

    // Set and normalize the base path so that it doesn't cause any trouble.
    $this->basePath = _acquia_purge_variable('acquia_purge_base_path');
    if ($this->basePath !== '/') {
      $this->basePath = '/' . trim(trim($this->basePath, '//'), '/') . '/';
    }
  }

  /**
   * Queue a single path.
   *
   * Add a path to the queue, meant for later processing. When late runtime
   * processing is enabled, processing of the queue likely happens during this
   * same request, but is not a guarantee. With cron-processing enabled, the
   * added item can get processed from there. When adding items as logged-in
   * Drupal user, the client-side AJAX processor will show up for this user when
   * rendering any (authenticated) page. See the FAQ on instructions on how to
   * process the queue yourself, although this is not recommended with the
   * already available means of processing.
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

    // Queue the item when it is unique and trigger the processors.
    if (!$this->deduplicate($path)) {
      if ($this->queue()->createItem(array($path))) {
        $this->processors()->emit('onItemsQueued');
      }
    }

    return $this->stats();
  }

  /**
   * Queue several paths.
   *
   * Add paths to the queue, meant for later processing. When late runtime
   * processing is enabled, processing of the queue likely happens during this
   * same request, but is not a guarantee. With cron-processing enabled, the
   * added item can get processed from there. When adding items as logged-in
   * Drupal user, the client-side AJAX processor will show up for this user when
   * rendering any (authenticated) page. See the FAQ on instructions on how to
   * process the queue yourself, although this is not recommended with the
   * already available means of processing.
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

    // Queue the items and trigger the processors to start processing.
    if ($this->queue()->createItemMultiple($items)) {
      $this->processors()->emit('onItemsQueued');
    }

    return $this->stats();
  }

  /**
   * Retrieve the capacity tracker service.
   *
   * @return AcquiaPurgeCapacity
   *   The capacity tracker object.
   */
  public function capacity() {
    if (is_null($this->capacity)) {
      $class = _acquia_purge_load('_acquia_purge_capacity');
      $this->capacity = new $class($this);
    }
    return $this->capacity;
  }

  /**
   * Empty the queue and reset all state data.
   */
  public function clear() {
    $this->lockRelease();
    $this->state()->wipe();
    $this->queue()->deleteQueue();
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
   * @param string $path
   *   The Drupal path (for example: '<front>', 'user/1' or a alias).
   * @param string $list
   *   (optional) Two breadcrumb lists are kept, 'queued' for preventative
   *   deduplication and 'purged' for keeping a post-purge track record.
   * @param int $l
   *   (optional) The $l parameter stands for 'limit' and represents the
   *   amount of items in a list to be crossed before it gets emptied.
   *
   * @warning
   *   Duplicated paths can still end up in the queue, especially when not using
   *   the 'acquia_purge_memcache' setting.
   *
   * @return true|false
   *   TRUE when the path is in the given list, FALSE when not.
   */
  public function deduplicate($path, $list = 'queued', $l = 500) {
    $memcached_backed_storage = $this->hostingInfo()->isMemcachedUsed();

    // And then each $list gets its own subsection.
    if (!isset($this->deduplicateLists[$list])) {
      $this->deduplicateLists[$list] = array();
      if ($memcached_backed_storage) {
        $this->deduplicateLists[$list] = $this->state()
          ->get($list, array())
          ->get();
      }
    }

    // Check if it exists before list rotation, then add missing items.
    $exists = in_array($path, $this->deduplicateLists[$list]);
    if (count($this->deduplicateLists[$list]) >= $l) {
      $this->deduplicateLists[$list] = array();
    }
    if (!$exists) {
      $this->deduplicateLists[$list][] = $path;
      if ($memcached_backed_storage) {
        $this->state()->get($list, array())->set($this->deduplicateLists[$list]);
      }
    }

    return $exists;
  }

  /**
   * Retrieve the AcquiaPurgeDiagnostics object.
   *
   * @return AcquiaPurgeDiagnostics
   *   The diagnostics service.
   */
  public function diagnostics() {
    if (is_null($this->diagnostics)) {
      $class = _acquia_purge_load('_acquia_purge_diagnostics');
      $this->diagnostics = new $class($this);
    }
    return $this->diagnostics;
  }

  /**
   * Retrieve IDs of the loaded executor plugins.
   *
   * @return string[]
   *   Non-associative array with plugin IDs.
   */
  public function executorIds() {
    $ids = array();
    foreach ($this->executors() as $executor) {
      $ids[] = $executor->getId();
    }
    return $ids;
  }

  /**
   * Retrieve the AcquiaPurgeExecutorsService object.
   *
   * @return AcquiaPurgeExecutorsService
   *   The executors service.
   */
  protected function executors() {
    if (is_null($this->executors)) {
      $class = _acquia_purge_load('_acquia_purge_executors');
      $this->executors = new $class($this);
    }
    return $this->executors;
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
   * Retrieve the hosting info object.
   *
   * @return AcquiaPurgeHostingInfo
   *   The hosting info object.
   */
  public function hostingInfo() {
    if (is_null($this->hostingInfo)) {
      $class = _acquia_purge_load('_acquia_purge_hosting_info');
      $this->hostingInfo = new $class();
    }
    return $this->hostingInfo;
  }

  /**
   * Retrieve permission to process the queue.
   *
   * @return true|false
   *   TRUE when the lock is acquired, FALSE when the queue is locked.
   */
  public function lockAcquire() {
    if ($this->lockActive()) {
      return FALSE;
    }
    $this->state()->get('locked', FALSE)->set(time());
    $this->state()->commit();
    return TRUE;
  }

  /**
   * Check if the queue is locked.
   *
   * @return true|false
   *   TRUE when locked, FALSE when it is not (or when a lock expired).
   */
  public function lockActive() {
    $locked = $this->state()->get('locked', FALSE)->get();
    if (is_int($locked)) {
      if ((time() - $locked) <= 60) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Release a lock set on the queue.
   */
  public function lockRelease() {
    $locked = $this->state()->get('locked', FALSE);
    if ($locked->get() !== FALSE) {
      $locked->set(FALSE);
      $this->state()->commit();
    }
  }

  /**
   * Retrieve the AcquiaPurgeOddities object.
   *
   * @return AcquiaPurgeOddities
   *   The oddities service.
   */
  public function oddities() {
    if (is_null($this->oddities)) {
      $class = _acquia_purge_load('_acquia_purge_oddities');
      $this->oddities = new $class($this);
    }
    return $this->oddities;
  }

  /**
   * Process as many items from the queue as the runtime capacity allows.
   *
   * @return bool
   *   Returns TRUE when it processed items, FALSE when the capacity limit has
   *   been reached or when the queue is empty and there's nothing left to do.
   */
  public function process() {

    // Do not even attempt to process when the total counter is zero.
    if ($this->queue()->total()->get() === 0) {
      return FALSE;
    }

    // How much can we safely process during this request?
    $maxitems = $this->capacity()->queueClaimsLimit();
    if ($maxitems < 1) {
      return FALSE;
    }

    // Ask the diagnostic subsystem to identify ACQUIA_PURGE_SEVLEVEL_ERROR
    // level severities, which mandate processing to stop and log the problem.
    if ($err = $this->diagnostics()->isSystemBlocked()) {
      $this->diagnostics()->log($err);
      return FALSE;
    }

    // Claim a number of items we can maximally process during request lifetime.
    if (!($queue_items = $this->queue()->claimItemMultiple($maxitems))) {
      $this->state()->wipe();
      return FALSE;
    }

    // Setup the $succeeded and $failed lists and fill $invalidations. Make sure
    // that already processed paths are immediately dismissed.
    $succeeded = $failed = $invalidations = array();
    foreach ($queue_items as $i => $item) {
      if ($this->deduplicate($item->getPath(), 'purged')) {
        $succeeded[] = $item;
        unset($queue_items[$i]);
        continue;
      }
      foreach ($this->hostingInfo()->getSchemes() as $s) {
        foreach ($this->hostingInfo()->getDomains() as $d) {
          $invalidations[] = $item->getInvalidation($s, $d, $this->basePath);
        }
      }
    }

    // Iterate each executor and feed the invalidations to all of them.
    foreach ($this->executors() as $executor) {
      $executor_id = $executor->getId();
      foreach ($invalidations as $i) {
        $i->setStatusContext($executor_id);
      }
      $executor->invalidate($invalidations);
    }

    // Put each and every invalidation back into general context.
    foreach ($invalidations as $i) {
      $i->setStatusContext(NULL);
      if ($i->getStatusBoolean() === TRUE) {
        $this->history($i->getUri());
      }
    }

    // In reality, $invalidation::setStatus() has kept the statuses on the queue
    // item, so we can now use ::getStatusBoolean() to delete/release items.
    foreach ($queue_items as $item) {
      if ($item->getStatusBoolean() === TRUE) {
        $this->deduplicate($item->getPath(), 'purged');
        $succeeded[] = $item;
      }
      else {
        $failed[] = $item;
      }
    }
    $this->queue()->deleteItemMultiple($succeeded);
    $this->queue()->releaseItemMultiple($failed);

    // Adjust the remaining capacity downwards for future ::process() calls.
    $this->capacity()->queueClaimsSubtract(count($succeeded) + count($failed));

    // When the bottom of the queue has been reached, reset all state data.
    if ($this->queue()->numberOfItems() === 0) {
      $this->state()->wipe();
    }

    // Invoke deprecated hook_acquia_purge_purge_(failure|success) hooks!
    if (count($failed)) {
      foreach (module_implements('acquia_purge_purge_failure') as $module) {
        $function = $module . '_acquia_purge_purge_failure';
        _acquia_purge_deprecated('hook_acquia_purge_executors()', $function);
        $function($this->queueItemPaths($failed));
      }
    }
    if (count($succeeded)) {
      foreach (module_implements('acquia_purge_purge_success') as $module) {
        $function = $module . '_acquia_purge_purge_success';
        _acquia_purge_deprecated('hook_acquia_purge_executors()', $function);
        $function($this->queueItemPaths($succeeded));
      }
    }

    return TRUE;
  }

  /**
   * Retrieve the AcquiaPurgeProcessorsService object.
   *
   * @return AcquiaPurgeProcessorsService
   *   The processors service.
   */
  public function processors() {
    if (is_null($this->processors)) {
      $class = _acquia_purge_load('_acquia_purge_processors');
      $this->processors = new $class($this);
    }
    return $this->processors;
  }

  /**
   * Retrieve the loaded queue backend object.
   *
   * @return AcquiaPurgeQueueInterface
   *   The queue backend.
   */
  public function queue() {
    if (is_null($this->queue)) {

      // Assure that all dependent code is loaded, lets not rely on registry.
      $state = $this->state();

      // Load the configured smart or normal backend.
      _acquia_purge_load('_acquia_purge_queue_interface');
      if (_acquia_purge_variable('acquia_purge_smartqueue')) {
        $class = _acquia_purge_load('_acquia_purge_queue_smart');
        $this->queue = new $class($state);
      }
      else {
        $class = _acquia_purge_load('_acquia_purge_queue_efficient');
        $this->queue = new $class($state);
      }
    }
    return $this->queue;
  }

  /**
   * DEPRECATED: Filter out the HTTP path from the given queue item object.
   *
   * @deprecated
   */
  protected function queueItemPath($item) {
    _acquia_purge_deprecated('$queue_item->getPath()');
    return $item->getPath();
  }

  /**
   * Filter out the HTTP path from a list of queue item objects.
   *
   * @param array $items
   *   Non-associative array with item objects, each object has at least the
   *   following properties (see AcquiaPurgeQueueInterface::claimItem()):
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   *
   * @return string[]
   *   Non-associative array with the HTTP paths to be purged.
   */
  protected function queueItemPaths(array $items) {
    $paths = array();
    foreach ($items as $item) {
      $paths[] = $item->getPath();
    }
    return $paths;
  }

  /**
   * Retrieve the state storage object.
   *
   * @return AcquiaPurgeStateStorageInterface
   *   The state storage backend.
   */
  public function state() {

    // Initialize the state storage backend.
    if (is_null($this->state)) {
      _acquia_purge_load('_acquia_purge_state_storage_interface');
      _acquia_purge_load('_acquia_purge_state_storage_base');
      if ($this->hostingInfo()->isMemcachedUsed()) {
        $class = _acquia_purge_load('_acquia_purge_state_storage_memcache');
        $this->state = new $class(
          ACQUIA_PURGE_STATE_MEMKEY,
          ACQUIA_PURGE_STATE_MEMBIN
        );
      }
      else {
        $class = _acquia_purge_load('_acquia_purge_state_storage_disk');
        $this->state = new $class(ACQUIA_PURGE_STATE_FILE);
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
      'locked' => $this->lockActive(),
      'total' => $this->queue()->total()->get(),
      'good' => $this->queue()->good()->get(),
      'bad' => $this->queue()->bad()->get(),
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

}
