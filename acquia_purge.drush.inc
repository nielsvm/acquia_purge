<?php

/**
 * @file
 * Drush integration providing common maintenance tasks.
 * @author
 * Niels van Mourik - Acquia Client Advisory <niels.vanmourik@acquia.com>
 */

/**
 * Implements hook_drush_help().
 */
function acquia_purge_drush_help($section) {
  switch ($section) {
    case 'meta:acquia_purge:title':
      return dt('Acquia Purge commands');
    case 'meta:acquia_purge:summary':
      return dt('Manage and create scheduled purges.');
  }
}

/**
 * Implements hook_drush_command().
 */
function acquia_purge_drush_command() {
  return array(

    // Define the 'ap-purge' command.
    'ap-purge' => array(
      'description' => 'Purge a specified path from your balancers.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('app'),
      'arguments' => array(
        'path' => 'The Drupal path to be purged, e.g.: "/news" or "<front>".'
      ),
      'examples' => array('drush app "/news"'),
    ),

    // Define the 'ap-forget' command.
    'ap-forget' => array(
      'description' => 'Forget all scheduled purges and empty the queue.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('apf'),
      'examples' => array('drush apf'),
    ),

    // Define the 'ap-process' command.
    'ap-process' => array(
      'description' => 'Purge all queued items from the command line.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('appr'),
      'examples' => array('drush appr'),
    ),
  );
}

/**
 * Purge a specified path from your balancers.
 *
 * @param string $path
 *   The Drupal path (e.g. '<front>', 'user/1' or an aliased path).
 */
function drush_acquia_purge_ap_purge($path = NULL) {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  // Check if the path was provided.
  if (!is_string($path)) {
    return drush_set_error("You haven't provided a path to be purged.");
  }

  // Block invocations with URL's instead of paths, we'll handle the URLs!
  if (stristr($path, 'http:') || stristr($path, 'https:')) {
    return drush_set_error("You can't provide a URL, only paths...");
  }

  // Acquire a lock and ensure nobody else is purging something.
  if (!lock_acquire('acquia_purge_ajax_processor', 60)) {
    return drush_set_error("Unable to acquire lock, ensure that all users "
      . "close their browser tabs as a different purge seems to be active!");
  }

  // Strip the path and remove potentially added double paths.
  $path = trim($path, '/');
  $path = str_replace('//', '/', $path);

  // If the path is empty, assume <front>.
  if (empty($path)) {
    $path = '<front>';
  }

  // Add the given path to the queue.
  $stats = acquia_purge_purge_path($path);

  // Calculate how many iterations we need and start processing accordingly!
  $rounds = ceil($stats['total'] / ACQUIA_PURGE_QUEUE_TRESHOLD);
  $log = array();
  for ($i = 0; $i < $rounds; $i++) {
    _acquia_purge_queue_pop('_acquia_purge_queue_processpurge');

    // Retrieve the statistics and log the purged urls.
    $stats = _acquia_purge_queue_stats();
    foreach ($stats['purgehistory'] as $logitem) {
      if (!(in_array($logitem, $log))) {
        drush_log(dt("Purged: @url", array('@url' => $logitem)), 'ok');
        $log[] = $logitem;
      }
    }
  }

  // We are done so lets release the lock.
  lock_release('acquia_purge_ajax_processor');
}

/**
 * Forget all scheduled purges and empty the queue.
 */
function drush_acquia_purge_ap_forget() {
  return drush_set_error('Not yet implemented, sorry...');
}

/**
 * Purge all queued items from the command line.
 */
function drush_acquia_purge_ap_process() {
  return drush_set_error('Not yet implemented, sorry...');
}
