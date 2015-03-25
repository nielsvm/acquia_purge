<?php

/**
 * @file
 * API functions and helper code past their expiration date.
 */

/**
 * Retrieve a variable or state data property.
 *
 * @deprecated
 */
function _acquia_purge_get($name, $get_default = FALSE) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_variable()'),
    WATCHDOG_WARNING);
  try {
    return _acquia_purge_variable($name);
  }
  catch (Exception $e) {
    return _acquia_purge_qs()->state()->get($name)->get();
  }
}

/**
 * DEPRECATED: return the queue object in use for Acquia Purge.
 *
 * @deprecated
 */
function _acquia_purge_queue() {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->queue()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->queue();
}

/**
 * DEPRECATED: clear the queue and invalidate all running processes.
 *
 * @deprecated
 */
function _acquia_purge_queue_clear($state_only = FALSE) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->clear()'),
    WATCHDOG_WARNING);
  if ($state_only) {
    _acquia_purge_qs()->state()->wipe();
  }
  else {
    _acquia_purge_qs()->clear();
  }
}

/**
 * DEPRECATED: help prevent duplicate path queuing and purging.
 *
 * @deprecated
 */
function _acquia_purge_queue_deduplicate($path, $list = 'queued', $l = 500) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->deduplicate()'
      ), WATCHDOG_WARNING);
  return _acquia_purge_qs()->deduplicate($path, $list, $l);
}

/**
 * DEPRECATED: maintains a runtime list of purged URLs for UI visualization.
 *
 * @deprecated
 */
function _acquia_purge_queue_history($url = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->history()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->history($url);
}

/**
 * DEPRECATED: acquire a lock and get permission to process the queue.
 *
 * @deprecated
 */
function _acquia_purge_queue_lock($acquire = TRUE) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->lock()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->lock($acquire);
}

/**
 * DEPRECATED: process as many items from the queue as capacity allows.
 *
 * @deprecated
 */
function _acquia_purge_queue_pop($processor = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->process()'),
    WATCHDOG_WARNING);
  if (is_null($processor)) {
    $processor = '_acquia_purge_purge';
  }
  return _acquia_purge_qs()->process($processor);
}

/**
 * DEPRECATED: purge a single path on all domains and load balancers.
 *
 * @deprecated
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
 */
function _acquia_purge_queue_stats($key = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->stats()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->stats($key);
}

/**
 * DEPRECATED: load state data from the persistent storage location.
 *
 * @deprecated
 */
function _acquia_purge_state_initialize() {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->state()'),
    WATCHDOG_WARNING);
  _acquia_purge_qs()->state();
}

/**
 * DEPRECATED: commit the state data to its persistent storage location.
 *
 * @deprecated
 */
function _acquia_purge_state_commit() {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->state()->commit()'),
    WATCHDOG_WARNING);
  _acquia_purge_qs()->state()->commit();
}

/**
 * DEPRECATED: load a state data property.
 *
 * @deprecated
 */
function _acquia_purge_state_get($name, $default = NULL) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->state()->get($name)->get()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->state()->get($name, $default)->get();
}

/**
 * DEPRECATED: store a state data property.
 *
 * @deprecated
 */
function _acquia_purge_state_set($name, $value) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->state()->get($name)->set($value)'),
    WATCHDOG_WARNING);
  _acquia_purge_qs()->state()->get($name)->set($value);
}

/**
 * DEPRECATED: wipe all state data.
 *
 * @deprecated
 */
function _acquia_purge_state_wipe() {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array(
      '%old' => __FUNCTION__,
      '!new' => '_acquia_purge_qs()->state()->wipe()'),
    WATCHDOG_WARNING);
  _acquia_purge_qs()->state()->wipe();
}

/**
 * DEPRECATED: queue a path and trigger the UI processor (if not using cron).
 *
 * @deprecated
 */
function acquia_purge_purge_path($path) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->addPath()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->addPath($path);
}

/**
 * DEPRECATED: queue paths and trigger the UI processor (if not using cron).
 *
 * @deprecated
 */
function acquia_purge_purge_paths(array $paths) {
  watchdog(
    'acquia_purge',
    "Function %old() is deprecated, port your code to !new immediately!",
    array('%old' => __FUNCTION__, '!new' => '_acquia_purge_qs()->addPaths()'),
    WATCHDOG_WARNING);
  return _acquia_purge_qs()->addPaths($paths);
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
