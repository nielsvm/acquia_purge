<?php

/**
 * @file
 * Deprecated functions.
 *
 * Version 7.x-1.2 deprecated many functions and reorganized everything in a
 * object oriented core. To prevent third-party code from just breaking, the old
 * symbols are kept in this file and wrap around the new objects.
 *
 * Nonetheless, this file is likely to go away in future versions, so the
 * recommendation is to port your immediately!
 */

/**
 * Log about a deprecated helper being used.
 *
 * @param string $new
 *   Suggested code replacement.
 * @param string|null $caller
 *   Caller that is deprecated, leave NULL for automatic backtrace lookup.
 */
function _acquia_purge_deprecated($new, $caller = NULL) {
  $msg = "!old() is deprecated, port your code to !new immediately!";
  $backtrace = debug_backtrace();
  if ($caller == NULL) {
    $caller = $backtrace[1]['function'];
  }
  $replacements = array('!old' => $caller, '!new' => $new);
  watchdog('acquia_purge', $msg, $replacements, WATCHDOG_WARNING);
}

/**
 * DEPRECATED: Determine whether we are running on Acquia Cloud or not.
 *
 * @deprecated
 */
function _acquia_purge_are_we_on_acquiacloud() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->isThisAcquiaCloud()');
  return _acquia_purge_service()->hostingInfo()->isThisAcquiaCloud();
}

/**
 * DEPRECATED: Retrieve a variable or state data property.
 *
 * @deprecated
 */
function _acquia_purge_get($name, $get_default = FALSE) {
  _acquia_purge_deprecated('_acquia_purge_variable()');
  try {
    return _acquia_purge_variable($name);
  }
  catch (Exception $e) {
    return _acquia_purge_service()->state()->get($name)->get();
  }
}

/**
 * DEPRECATED: Get a list of bal IP addresses in front of this site.
 *
 * @deprecated
 */
function _acquia_purge_get_balancers() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->getBalancerAddresses()');
  return _acquia_purge_service()->hostingInfo()->getBalancerAddresses();
}

/**
 * DEPRECATED: Get a list of defined domains that we can purge for.
 *
 * @deprecated
 */
function _acquia_purge_get_domains() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->getDomains()');
  return _acquia_purge_service()->hostingInfo()->getDomains();
}

/**
 * DEPRECATED: Add a domain to the domain list.
 *
 * @deprecated
 */
function _acquia_purge_get_domains_add($domain, array &$domains) {
  _acquia_purge_deprecated('$domains[] = "new domain";');
  $domains[] = $domain;
}

/**
 * DEPRECATED: Get a list of protocol schemes that will be purged.
 *
 * @deprecated
 */
function _acquia_purge_get_protocol_schemes() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->getSchemes()');
  return _acquia_purge_service()->hostingInfo()->getSchemes();
}

/**
 * DEPRECATED: Determine the Acquia site name.
 *
 * @deprecated
 */
function _acquia_purge_get_site_name() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->getSiteName()');
  return _acquia_purge_service()->hostingInfo()->getSiteName();
}

/**
 * DEPRECATED: Determine the Acquia site group.
 *
 * @deprecated
 */
function _acquia_purge_get_site_group() {
  _acquia_purge_deprecated('_acquia_purge_service()->hostingInfo()->getSiteGroup()');
  return _acquia_purge_service()->hostingInfo()->getSiteGroup();
}

/**
 * DEPRECATED: Process the HTTP requests for a single purge.
 *
 * @deprecated
 */
function _acquia_purge_purge_requests($requests) {
  _acquia_purge_deprecated('_acquia_purge_curl()');
  return _acquia_purge_curl($requests);
}

/**
 * DEPRECATED: Check if the current request path is blacklisted.
 *
 * @deprecated
 */
function _acquia_purge_ui_path_blacklisted() {
  _acquia_purge_deprecated('AcquiaPurgeProcessorAjax::isPathBlacklisted()');
  return _acquia_purge_service()
    ->processors()
    ->get('AcquiaPurgeProcessorAjax')
    ->isPathBlacklisted();
}

/**
 * DEPRECATED: Trigger client-side AJAX based purging during this request.
 *
 * @deprecated
 */
function _acquia_purge_ui_trigger_purge() {
  _acquia_purge_deprecated('AcquiaPurgeProcessorAjax::initializeClientSideProcessor()');
  // Call onInit, which will call initializeClientSideProcessor when it should.
  _acquia_purge_service()->processors()->get('AcquiaPurgeProcessorAjax')->onInit();
}

/**
 * DEPRECATED: return the queue object in use for Acquia Purge.
 *
 * @deprecated
 */
function _acquia_purge_queue() {
  _acquia_purge_deprecated('_acquia_purge_service()->queue()');
  return _acquia_purge_service()->queue();
}

/**
 * DEPRECATED: add a single purge to the queue.
 *
 * @deprecated
 */
function _acquia_purge_queue_add($path) {
  _acquia_purge_deprecated('_acquia_purge_service()->addPath()');
  return _acquia_purge_service()->addPath($path);
}

/**
 * DEPRECATED: clear the queue and invalidate all running processes.
 *
 * @deprecated
 */
function _acquia_purge_queue_clear($state_only = FALSE) {
  _acquia_purge_deprecated('_acquia_purge_service()->clear()');
  if ($state_only) {
    _acquia_purge_service()->state()->wipe();
  }
  else {
    _acquia_purge_service()->clear();
  }
}

/**
 * DEPRECATED: help prevent duplicate path queuing and purging.
 *
 * @deprecated
 */
function _acquia_purge_queue_deduplicate($path, $list = 'queued', $l = 500) {
  _acquia_purge_deprecated('_acquia_purge_service()->deduplicate()');
  return _acquia_purge_service()->deduplicate($path, $list, $l);
}

/**
 * DEPRECATED: maintains a runtime list of purged URLs for UI visualization.
 *
 * @deprecated
 */
function _acquia_purge_queue_history($url = NULL) {
  _acquia_purge_deprecated('_acquia_purge_service()->history()');
  return _acquia_purge_service()->history($url);
}

/**
 * DEPRECATED: Determines if the current user owns a running purge session.
 *
 * @deprecated
 */
function _acquia_purge_queue_is_user_purging() {
  _acquia_purge_deprecated('AcquiaPurgeProcessorAjax::isUserOwningTheQueue()');
  // Since ::isUserOwningTheQueue is static, call it through a small loophole.
  $service = _acquia_purge_service();
  $ajaxprocessor = $service->processors()->get('AcquiaPurgeProcessorAjax');
  return $ajaxprocessor::isUserOwningTheQueue($service);
}

/**
 * DEPRECATED: acquire a lock and get permission to process the queue.
 *
 * @deprecated
 */
function _acquia_purge_queue_lock($acquire = TRUE) {
  _acquia_purge_deprecated('_acquia_purge_service()->lockAcquire()');
  if ($acquire === NULL) {
    _acquia_purge_service()->lockRelease();
  }
  else {
    return _acquia_purge_service()->lockAcquire();
  }
}

/**
 * DEPRECATED: process as many items from the queue as capacity allows.
 *
 * @deprecated
 */
function _acquia_purge_queue_pop($processor = NULL) {
  _acquia_purge_deprecated('_acquia_purge_service()->process()');
  if (is_null($processor)) {
    $processor = '_acquia_purge_purge';
  }
  return _acquia_purge_service()->process($processor);
}

/**
 * DEPRECATED: purge a single path on all domains and load balancers.
 *
 * @deprecated
 */
function _acquia_purge_queue_processpurge($path) {
  _acquia_purge_deprecated('_acquia_purge_purge()');
  return _acquia_purge_purge($path);
}

/**
 * DEPRECATED: process the given HTTP requests and do it efficiently.
 *
 * @deprecated
 */
function _acquia_purge_queue_processpurge_requests($requests) {
  _acquia_purge_deprecated('_acquia_purge_curl()');
  return _acquia_purge_curl($requests);
}

/**
 * DEPRECATED: generate progress statistics on the purge queue.
 *
 * @deprecated
 */
function _acquia_purge_queue_stats($key = NULL) {
  _acquia_purge_deprecated('_acquia_purge_service()->stats()');
  return _acquia_purge_service()->stats($key);
}

/**
 * DEPRECATED: load state data from the persistent storage location.
 *
 * @deprecated
 */
function _acquia_purge_state_initialize() {
  _acquia_purge_deprecated('_acquia_purge_service()->state()');
  _acquia_purge_service()->state();
}

/**
 * DEPRECATED: commit the state data to its persistent storage location.
 *
 * @deprecated
 */
function _acquia_purge_state_commit() {
  _acquia_purge_deprecated('_acquia_purge_service()->state()->commit()');
  _acquia_purge_service()->state()->commit();
}

/**
 * DEPRECATED: load a state data property.
 *
 * @deprecated
 */
function _acquia_purge_state_get($name, $default = NULL) {
  _acquia_purge_deprecated('_acquia_purge_service()->state()->get($name)->get()');
  return _acquia_purge_service()->state()->get($name, $default)->get();
}

/**
 * DEPRECATED: store a state data property.
 *
 * @deprecated
 */
function _acquia_purge_state_set($name, $value) {
  _acquia_purge_deprecated('_acquia_purge_service()->state()->get($name)->set($value)');
  _acquia_purge_service()->state()->get($name)->set($value);
}

/**
 * DEPRECATED: wipe all state data.
 *
 * @deprecated
 */
function _acquia_purge_state_wipe() {
  _acquia_purge_deprecated('_acquia_purge_service()->state()->wipe()');
  _acquia_purge_service()->state()->wipe();
}

/**
 * DEPRECATED: queue a path and trigger the UI processor (if not using cron).
 *
 * @deprecated
 */
function acquia_purge_purge_path($path) {
  _acquia_purge_deprecated('_acquia_purge_service()->addPath()');
  return _acquia_purge_service()->addPath($path);
}

/**
 * DEPRECATED: queue paths and trigger the UI processor (if not using cron).
 *
 * @deprecated
 */
function acquia_purge_purge_paths(array $paths) {
  _acquia_purge_deprecated('_acquia_purge_service()->addPaths()');
  return _acquia_purge_service()->addPaths($paths);
}

/**
 * DEPRECATED: Purge the paths from a node from Varnish.
 *
 * @deprecated
 *
 * @see expire_node_insert()
 * @see expire_node_update()
 * @see expire_node_delete()
 */
function acquia_purge_purge_node(&$node) {
  _acquia_purge_deprecated('expire_node_*()');

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
