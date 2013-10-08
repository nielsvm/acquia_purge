<?php

/**
 * @file
 * Admin page callbacks and theme functions for the Acquia Purge module.
 */

/**
 * Menu callback; process a chunk of purge items via AJAX.
 *
 * @returns
 *   Statistics array encoded as JSON, including a 'widget' HTML snippet.
 */
function acquia_purge_ajax_processor() {
  $status = _acquia_purge_queue_stats();

  // Define a key 'error' that describes a potential error condition.
  $status['error'] = $status['locked'] = FALSE;

  // Lock acquiring assures us that nothing is purging the same paths at the
  // same time. All ways that trigger purging (ajax, drush) respect the locks.
  if (lock_acquire('acquia_purge_ajax_processor', ACQUIA_PURGE_QUEUE_LOCK_TIMEOUT)) {

    // Pop items from the queue and immediately process them.
    _acquia_purge_queue_pop('_acquia_purge_queue_processpurge');

    // Refresh the statistics post-run, so override most fields in $status.
    foreach (_acquia_purge_queue_stats() as $key => $value) {
      $status[$key] = $value;
    }

    // Ask our built-in diagnostics system to preliminary find issues that are
    // so risky we can expect problems. Everything starting with
    // ACQUIA_PURGE_SEVLEVEL_ERROR will cause purging to cease and log messages
    // to be written. Warn the user on any of them.
    if (count($e = _acquia_purge_get_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR))) {
      $error = current($e);
      $status['error'] = $error['description_plain'];
    }

    // If no obvious errors were found by the diagnostics system, check if the
    // history list is remaining empty: another bad symptom.
    elseif (empty($status['purgehistory'])) {
      $status['error'] = t("The system seems to be having difficulties
        refreshing recent content changes. Your work won't be lost, but please
        do ask your technical administrator to check the logs.");
    }

    // We're done so lets release the lock.
    lock_release('acquia_purge_ajax_processor');
  }

  // Instead of raising a very visual error condition: indicate the lock.
  else {
    $status['locked'] = TRUE;
  }

  // Render the status widget and add it to the statistics array.
  $status['widget'] = theme('acquia_purge_status_bar_widget', $status);

  // Return the status array with statistics...
  return drupal_json_output($status);
}

/**
 * Returns HTML for the Acquia Purge status widget.
 *
 * @param array $variables
 *   An associative array containing:
 *   - total: Total amount of actions initially queued for purging.
 *   - remaining: Remaining number of purge actions still to be processed.
 *   - processed: Number of purge actions that have already taken place.
 *   - percent: Value between 0-100 representing the progress percentage.
 *   - running: Whether URLs are being purged or not.
 *   - purgehistory: Array with recently purged URL's.
 *
 * @ingroup themeable
 */
function theme_acquia_purge_status_widget($variables) {
  $progress_bar = theme('acquia_purge_status_bar_widget', $variables);

  // Prepare the table which we use as container.
  $table_variables = array(
    'header' => array(),
    'rows' => array(array($progress_bar)));

  // Render the history list and add it to the table.
  if (count($variables['purgehistory'])) {
    $table_variables['rows'][] = array(
      theme(
        'item_list',
        array(
          'items' => $variables['purgehistory'],
          'title' => t('Recently refreshed:'))),
    );
  }

  // Render the table.
  $table = theme('table', $table_variables);

  // Return the HTML.
  return '<div id="acquia-purge-status-widget">' . $table . '</div>';
}

/**
 * Returns HTML for the Acquia Purge progress bar widget.
 *
 * @param array $variables
 *   An associative array containing:
 *   - total: Total amount of actions initially queued for purging.
 *   - remaining: Remaining number of purge actions still to be processed.
 *   - processed: Number of purge actions that have already taken place.
 *   - percent: Value between 0-100 representing the progress percentage.
 *   - running: Whether URLs are being purged or not.
 *   - purgehistory: Array with recently purged URL's.
 *
 * @ingroup themeable
 */
function theme_acquia_purge_status_bar_widget($variables) {

  // Determine status messages based on what the statistics tells us.
  if ($variables['running']) {
    $message = t("Site content is being refreshed, please wait a moment...
    %remaining items to go...", array('%remaining' => $variables['remaining']));
  }
  else {
    $message = t("Content changes are now visible for everybody!");
  }

  return theme('progress_bar', array(
    'percent' => $variables['percent'], 'message' => $message));
}
