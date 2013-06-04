<?php

/**
 * @file
 * Admin page callbacks and theme functions for the Acquia Purge module.
 * @author
 * Niels van Mourik - Acquia Client Advisory <niels.vanmourik@acquia.com>
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
  $status['error'] = FALSE;

  // With ACQUIA_PURGE_QUEUE_TRESHOLD put on 10 and individual purge timeouts
  // on 3 seconds the theory goes that a run should never exceed 30 seconds. To
  // be on the safe side the lock we acquire expires after 60 seconds.
  if (lock_acquire('acquia_purge_ajax_processor', 60)) {

    // Pop items from the queue and immediately process them.
    _acquia_purge_queue_pop('_acquia_purge_queue_processpurge');

    // Refresh the statistics post-run, so override most fields in $status.
    foreach (_acquia_purge_queue_stats() as $key => $value) {
      $status[$key] = $value;
    }

    // Raise error condition if history list remains empty.
    if (empty($status['purgehistory'])) {
      $status['error'] = t("The process stalled and nothing is getting purged,
        failing purges will be retried endlessly until the problem is solved.
        Please check your log files.");
    }

    // We're done so lets release the lock.
    lock_release('acquia_purge_ajax_processor');
  }

  // Render the status widget and add it to the statistics array.
  $status['widget'] = theme('acquia_purge_status_bar_widget', $status);

  // Return the status array with statistics...
  return drupal_json_output($status);
}

/**
 * Returns HTML for the Acquia Purge status widget.
 *
 * @param $variables
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
          'title' => t('Recently refreshed:')))
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
 * @param $variables
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
    $message = t("Pages are being refreshed on your site, please wait!
    %remaining items to go...", array('%remaining' => $variables['remaining']));
  }
  else {
    $message = t("Finished");
  }

  return theme('progress_bar', array(
    'percent' => $variables['percent'], 'message' => $message));
}