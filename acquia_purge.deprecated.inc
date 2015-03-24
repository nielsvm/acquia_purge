<?PHP

/**
 * DEPRECATED: return the queue object in use for Acquia Purge.
 *
 * @deprecated
 * @see ApQueueService::queue()
 */
function _acquia_purge_queue() {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->queue()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->queue();
}

/**
 * DEPRECATED: clear the queue and invalidate all running processes.
 *
 * @param bool $state_only
 *   Only wipe associated state data as the queue is already certainly
 *   cleared, for instance when ApQueueInterface::numberOfItems() returned 0.
 *
 * @deprecated
 * @see ApQueueService::clear()
 * @see ApQueueService::clearState()
 */
function _acquia_purge_queue_clear($state_only = FALSE) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->clear()'),
    WATCHDOG_WARNING);
  if ($state_only) {
    ApQueueService::i()->clearState();
  }
  else {
    ApQueueService::i()->clear();
  }
}

/**
 * DEPRECATED: help prevent duplicate path queuing and purging.
 *
 * @deprecated
 * @see ApQueueService::deduplicate()
 */
function _acquia_purge_queue_deduplicate($path, $list = 'queued', $l = 500) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => 'ApQueueService::i()->deduplicate()'
      ), WATCHDOG_WARNING);
  return ApQueueService::i()->deduplicate($path, $list, $l);
}

/**
 * DEPRECATED: maintains a runtime list of purged URLs for UI visualization.
 *
 * @deprecated
 * @see ApQueueService::history()
 */
function _acquia_purge_queue_history($url = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->history()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->history($url);
}

/**
 * DEPRECATED: acquire a lock and get permission to process the queue.
 *
 * @deprecated
 * @see ApQueueService::lock()
 */
function _acquia_purge_queue_lock($acquire = TRUE) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->lock()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->lock($acquire);
}

/**
 * DEPRECATED: process as many items from the queue as capacity allows.
 *
 * @deprecated
 * @see ApQueueService::process()
 */
function _acquia_purge_queue_pop($processor = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->process()'),
    WATCHDOG_WARNING);
  if (is_null($processor)) {
    $processor = '_acquia_purge_purge';
  }
  return ApQueueService::i()->process($processor);
}

/**
 * DEPRECATED: purge a single path on all domains and load balancers.
 *
 * @deprecated
 * @see _acquia_purge_purge()
 */
function _acquia_purge_queue_processpurge($path) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_purge()'),
    WATCHDOG_WARNING);
  return _acquia_purge_purge($path);
}

/**
 * DEPRECATED: process the given HTTP requests and do it efficiently.
 *
 * @deprecated
 * @see _acquia_purge_purge_requests()
 */
function _acquia_purge_queue_processpurge_requests($requests) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_purge_requests()'),
    WATCHDOG_WARNING);
  return _acquia_purge_purge_requests($requests);
}

/**
 * DEPRECATED: generate progress statistics on the purge queue.
 *
 * @deprecated
 * @see ApQueueService::stats()
 */
function _acquia_purge_queue_stats($key = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->stats()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->stats($key);
}

/**
 * DEPRECATED: queue a path and trigger the UI processor (if not using cron).
 *
 * @deprecated
 * @see ApQueueService::addPath()
 */
function acquia_purge_purge_path($path) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->addPath()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->addPath($path);
}

/**
 * DEPRECATED: queue paths and trigger the UI processor (if not using cron).
 *
 * @deprecated
 * @see ApQueueService::addPaths()
 */
function acquia_purge_purge_paths(array $paths) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'ApQueueService::i()->addPaths()'),
    WATCHDOG_WARNING);
  return ApQueueService::i()->addPaths($paths);
}

/**
 * DEPRECATED: Purge the paths from a node from Varnish.
 *
 * @deprecated
 * @see expire_node_insert()
 * @see expire_node_update()
 * @see expire_node_delete()
 */
function acquia_purge_purge_node(&$node) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => 'expire_node_*()'),
    WATCHDOG_WARNING);

  $paths = array('node/' . $node->nid);
  if (isset($node->path['alias']) && !empty($node->path['alias'])) {
    $paths[] = $node->path['alias'];
  }
  if (isset($node->promote) && $node->promote) {
    $paths[] = '<front>';
    $paths[] = 'rss.xml';
  }

  // Return the paths routine and return the statistics from the queue manager.
  return acquia_purge_purge_paths($paths);
}
