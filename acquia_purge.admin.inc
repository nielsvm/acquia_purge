<?php

/**
 * @file
 * Admin page callbacks and theme functions for the Acquia Purge module.
 * @author
 * Niels van Mourik - Acquia Client Advisory <niels.vanmourik@acquia.com>
 */


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