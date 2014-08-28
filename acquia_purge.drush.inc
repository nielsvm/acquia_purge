<?php

/**
 * @file
 * Drush integration providing common maintenance tasks.
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

    // Define the 'ap-diagnosis' command.
    'ap-diagnosis' => array(
      'description' => 'Perform a series of diagnostic self-tests.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('apd'),
      'examples' => array('drush apd'),
    ),

    // Define the 'ap-domains' command.
    'ap-domains' => array(
      'description' => 'List all domains Acquia Purge will purge against.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('apdo'),
      'examples' => array('drush apdo'),
    ),

    // Define the 'ap-purge' command.
    'ap-purge' => array(
      'description' => 'Purge a specified path from your balancers.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('app'),
      'arguments' => array(
        'path' => 'The Drupal path to be purged, e.g.: "/news" or "<front>".',
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

    // Define the 'ap-list' command.
    'ap-list' => array(
      'description' => 'List all the items that are in the queue.',
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
      'aliases' => array('apl'),
      'examples' => array('drush apl'),
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
 * Perform a series of diagnostic self-tests.
 *
 * @param int $v
 *   Optional, the level of diagnostics presented. Test results that match or
 *   are higher than the given level are returned.
 */
function drush_acquia_purge_ap_diagnosis($v = ACQUIA_PURGE_SEVLEVEL_INFO) {
  $oldverbosity = drush_get_context('DRUSH_VERBOSE');
  $tests = _acquia_purge_get_diagnosis($v);

  // Define a mapping between install.inc's requirement states and drush_log().
  $severitymap = array(
    ACQUIA_PURGE_SEVLEVEL_INFO => 'info',
    ACQUIA_PURGE_SEVLEVEL_OK   => 'ok',
    ACQUIA_PURGE_SEVLEVEL_WARNING => 'warning',
    ACQUIA_PURGE_SEVLEVEL_ERROR   => 'error',
  );

  // Tell Drush to be verbose.
  drush_set_context('DRUSH_VERBOSE', TRUE);
  drush_set_context('DRUSH_COLUMNS', 120);

  // Determine the longest test name and set the drush_type field.
  $longest = 25;
  foreach ($tests as $id => $test) {
    $tests[$id]['drush_type'] = $severitymap[$test['severity']];
    if (strlen($test['name']) > $longest) {
      $longest = strlen($test['name']) + 2;
    }
  }

  // Iterate the tests and call drush_log with the appropriate message type.
  foreach ($tests as $test) {
    _drush_acquia_purge_ap_diagnosis_log($test, $longest);
  }

  // Set back the old verbosity state.
  drush_set_context('DRUSH_VERBOSE', $oldverbosity);
}

/**
 * Log the given test.
 *
 * @param array $test
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: ACQUIA_PURGE_SEVLEVEL_INFO, _OK, _WARNING, _ERROR

 * @param int $longest
 *   The longest test name, used to calculate formatting padding.
 */
function _drush_acquia_purge_ap_diagnosis_log($test, $longest) {

  // When running in backend mode, log messages aren't printed and forwarded.
  if (drush_get_context('DRUSH_BACKEND')) {
    $message = sprintf('%s: %s', $test['name'], $test['value_plain']);
    return drush_log($message, $test['drush_type']);
  }

  // When not running in backend mode, prepare for fancier output.
  $type = sprintf(' [%s]', $test['drush_type']);
  $pad_maxlength = 11;
  $colors = array(
    'info' => '%s',
    'error'  => '%s',
    'warning' => '%s',
    'ok' => '%s');

  // In color mode, setup ASCII color codes borrowed from _drush_print_log().
  if (!drush_get_context('DRUSH_NOCOLOR')) {
    $colors['error'] = "\033[31;40m\033[1m%s\033[0m";
    $colors['warning'] = "\033[1;33;40m\033[1m%s\033[0m";
    $colors['ok'] = "\033[1;32;40m\033[1m%s\033[0m";
  }

  // Print the test name and value.
  printf(
    sprintf($colors[$test['drush_type']], "%s%s%s%s%s\n"),
    $type,
    str_repeat(' ', $pad_maxlength - strlen($type)),
    strtoupper($test['name']),
    str_repeat(' ', $longest - strlen($test['name'])),
    strtolower($test['value_plain'])
  );

  // Print descriptions for warnings and errors.
  if ($test['severity'] >= ACQUIA_PURGE_SEVLEVEL_WARNING) {
    $pad = str_repeat(' ', $pad_maxlength);
    printf(
      sprintf($colors[$test['drush_type']], "\n%s%s\n\n"),
      $pad,
      wordwrap($test['description_plain'], 2 * $longest, "\n$pad")
    );
  }
}

/**
 * List all detected domain names that Acquia Purge will purge.
 */
function drush_acquia_purge_ap_domains() {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  foreach (_acquia_purge_get_domains() as $domain) {
    printf("%s\n", $domain);
  }
}

/**
 * Purge a specified path from your balancers.
 *
 * @param string $path
 *   The Drupal path (for example: '<front>', 'user/1' or a alias).
 */
function drush_acquia_purge_ap_purge($path = NULL) {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  // Stop invocation if serious error conditions have been found.
  if (!_acquia_purge_are_we_allowed_to_purge()) {
    return drush_acquia_purge_ap_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR);
  }

  // Validate the given user input.
  if ($msg = _acquia_purge_input_validate($path)) {
    return drush_set_error($msg);
  }

  // Add the given path to the queue and dispatch processing to ap-process.
  acquia_purge_purge_path($path);
  drush_acquia_purge_ap_process();
}

/**
 * Forget all scheduled purges and empty the queue.
 */
function drush_acquia_purge_ap_forget() {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  // Retrieve statistics, clear the queue and log to Drush.
  $stats = _acquia_purge_queue_stats();
  _acquia_purge_queue_clear();
  drush_log(dt("Removed @remaining items from the queue.",
    array('@remaining' => $stats['remaining'])), 'ok');

  // If there was a lock acquired, free it up as the queue will be empty now.
  lock_release(ACQUIA_PURGE_QUEUE_LOCK_ID);
}

/**
 * List all the items that are in the queue.
 */
function drush_acquia_purge_ap_list() {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  // Directly query the queue table and print all records.
  $items = db_select('queue', 'q')
    ->fields("q", array("data"))
    ->condition('name', 'acquia_purge')
    ->execute();
  while ($item = $items->fetchAssoc()) {
    if ($item = unserialize(current($item))) {
      printf(" - '%s'\n", current($item));
    }
  }
}

/**
 * Purge all queued items from the command line.
 */
function drush_acquia_purge_ap_process() {

  // Stop invocation if we are not detecting Acquia Cloud heuristics.
  if (!_acquia_purge_are_we_on_acquiacloud()) {
    return drush_set_error("You must be on Acquia Cloud to use Acquia Purge.");
  }

  // Acquire a lock and ensure nobody else is purging something.
  if (!lock_acquire(ACQUIA_PURGE_QUEUE_LOCK_ID, ACQUIA_PURGE_QUEUE_LOCK_TIMEOUT)) {
    return drush_set_error("Unable to acquire lock, ensure that all users "
      . "close their browser tabs as a different purge seems to be active!");
  }

  // Stop invocation if serious error conditions have been found.
  if (!_acquia_purge_are_we_allowed_to_purge()) {
    return drush_acquia_purge_ap_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR);
  }

  // Retrieve the statistics and determine if processing is needed.
  $stats = _acquia_purge_queue_stats();
  if (!$stats['running']) {
    return drush_set_error("The purge queue seems to be empty!");
  }

  // Calculate how many iterations we need and start processing accordingly.
  $log = array();
  do {
    $items = _acquia_purge_queue_pop('_acquia_purge_queue_processpurge');

    // Retrieve the statistics and log the purged urls.
    $stats = _acquia_purge_queue_stats();
    foreach ($stats['purgehistory'] as $logitem) {
      if (!(in_array($logitem, $log))) {
        drush_log(dt("Purged: @url", array('@url' => $logitem)), 'ok');
        $log[] = $logitem;
      }
    }

  } while (count($items));

  // We are done so lets release the lock.
  lock_release(ACQUIA_PURGE_QUEUE_LOCK_ID);
}
