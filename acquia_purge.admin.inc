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
      $status['error'] = $error['description'];
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
  return drupal_json($status);
}

/**
 * Menu callback to drupal_get_form; let users manually purge pages.
 *
 * @returns
 *   A standard Form API form-array.
 */
// function acquia_purge_manualpurge_form($form, &$form_state) {
//   $schemes = _acquia_purge_get_protocol_schemes();
//   $domains = _acquia_purge_get_domains();
//   $form['description'] = array(
//     '#markup' => t('<p>This form allows you to purge one or more paths from your
//     Acquia Cloud load balancers, e.g.: <b>news?page=1</b>. This functionality is
//     not intended for day-to-day use but rather for emergency cases when an
//     outdated copy of a page is served. It is highly recommended to automate your
//     site by creating rules for these paths, so everybody editing content can
//     rely on your site refreshing properly.</p>'),
//   );
//   $form['domains'] = array(
//     '#markup' => '<p>' . theme(
//       'item_list',
//       array(
//         'title' => t('Paths will be purged on:'),
//         'items' => $domains,
//       )
//     ) . '</p>',
//   );
//
//   // Because we have many fields with the same values, we have to set
//   // #tree to be able to access them.
//   $form['#tree'] = TRUE;
//
//   // We'll group all paths both in a HTML wrapper and logically in the form.
//   $title = t('Paths to be purged:');
//   $form['paths'] = array(
//     '#prefix' => '<div id="paths-wrapper"><p><h3>' . $title . '</h3><ul>',
//     '#suffix' => '</ul></p></div>',
//   );
//
//   // Fill paths with the right amount of form items according to num_paths.
//   $prefix = sprintf('<b>%s://%s%s</b>', $schemes[0], $domains[0], base_path());
//   if (empty($form_state['num_paths'])) {
//     $form_state['num_paths'] = 1;
//   }
//   for ($i = 0; $i < $form_state['num_paths']; $i++) {
//     $form['paths']['path'][$i] = array(
//       '#type' => 'textfield',
//       '#field_prefix' => $prefix,
//       '#size' => 30,
//       '#prefix' => '<li>',
//       '#suffix' => '</li>',
//     );
//   }
//
//   // Declare the add and remove buttons.
//   $form['paths']['add_path'] = array(
//     '#type' => 'submit',
//     '#value' => t('Add'),
//     '#submit' => array('acquia_purge_manualpurge_form_add_one'),
//     '#ajax' => array(
//       'callback' => 'acquia_purge_manualpurge_form_callback',
//       'wrapper' => 'paths-wrapper',
//     ),
//   );
//   if ($form_state['num_paths'] > 1) {
//     $form['paths']['remove_path'] = array(
//       '#type' => 'submit',
//       '#value' => t('Remove last item'),
//       '#submit' => array('acquia_purge_manualpurge_form_remove_one'),
//       '#ajax' => array(
//         'callback' => 'acquia_purge_manualpurge_form_callback',
//         'wrapper' => 'paths-wrapper',
//       ),
//     );
//   }
//
//   // Render the submit button and return the form.
//   $form['submit'] = array(
//     '#type' => 'submit',
//     '#value' => t("I know the risks, purge!"),
//   );
//   return $form;
// }

/**
 * Callback to drupal_get_form: the AJAX callback to paths-wrapper.
 */
// function acquia_purge_manualpurge_form_callback($form, $form_state) {
//   return $form['paths'];
// }

/**
 * Callback to drupal_get_form: add a path textfield.
 */
// function acquia_purge_manualpurge_form_add_one($form, &$form_state) {
//   $form_state['num_paths']++;
//   $form_state['rebuild'] = TRUE;
// }

/**
 * Callback to drupal_get_form: remove a path textfield.
 */
// function acquia_purge_manualpurge_form_remove_one($form, &$form_state) {
//   if ($form_state['num_paths'] > 1) {
//     $form_state['num_paths']--;
//   }
//   $form_state['rebuild'] = TRUE;
// }

/**
 * Callback to drupal_get_form: validate the form.
 */
// function acquia_purge_manualpurge_form_validate($form, &$form_state) {
//   $paths = array();
//   foreach ($form_state['values']['paths']['path'] as $id => $path) {
//     if (empty($path)) {
//       form_set_error('paths][path][' . $id,
//         t('The path can not be empty, use &lt;front&gt; for your frontpage!'));
//     }
//     elseif (!is_string($path)) {
//       form_set_error('paths][path][' . $id,
//         t("The path has to be a string!"));
//     }
//     elseif (stristr($path, 'http:') || stristr($path, 'https:')) {
//       form_set_error('paths][path][' . $id,
//         t("You can't provide a URL, only paths!"));
//     }
//     elseif (preg_match('/\s/', $path)) {
//       form_set_error('paths][path][' . $id,
//         t('The path can not contain a space!'));
//     }
//     elseif (in_array($path, $paths)) {
//       form_set_error('paths][path][' . $id,
//         t('You have already listed this path!'));
//     }
//     $paths[] = $path;
//   }
// }

/**
 * Callback to drupal_get_form: handle the form submit.
 */
// function acquia_purge_manualpurge_form_submit($form, &$form_state) {
//   acquia_purge_purge_paths($form_state['values']['paths']['path']);
// }

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

  return theme('progress_bar', $variables['percent'], $message);
}
