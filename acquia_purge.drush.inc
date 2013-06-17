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
function drush_acquia_purge_ap_purge($path) {
  return drush_set_error('Not yet implemented, sorry...');
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
