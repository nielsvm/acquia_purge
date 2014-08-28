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
  $stats = _acquia_purge_queue_stats();
  $stats['widget'] = '&nbsp;';
  $stats['locked'] = FALSE;
  $stats['error'] = FALSE;

  // Ask our diagnostics system if there are any blocking issues that should
  // stop us from processing the queue and notify the user about it.
  if (!_acquia_purge_are_we_allowed_to_purge()) {
    $err = current(_acquia_purge_get_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR));
    _acquia_purge_get_diagnosis_logged($err);
    $stats['error'] = $err['description'];
  }

  // If nothing was found, attempt to acquire the lock and process some items.
  else {
    if (lock_acquire(ACQUIA_PURGE_QUEUE_LOCK_ID, ACQUIA_PURGE_QUEUE_LOCK_TIMEOUT)) {

      // This automatically pops an X amount of items for which we have enough
      // resource capacity and processes them straight away.
      _acquia_purge_queue_pop('_acquia_purge_queue_processpurge');

      // Processing finished, so update $stats to reflect the new values.
      foreach (_acquia_purge_queue_stats() as $key => $value) {
        $stats[$key] = $value;
      }

      // If errors did occur meanwhile, one indication of this could be that
      // the history list remained empty, raise an error if it is empty.
      if (empty($stats['purgehistory'])) {
        $stats['error'] = t("The system seems to be having difficulties
          refreshing recent content changes. Your work won't be lost, but please
          do ask your technical administrator to check the logs.");
      }

      lock_release(ACQUIA_PURGE_QUEUE_LOCK_ID);
    }
    else {
      $stats['locked'] = TRUE;
    }
  }

  // Render the status widget and add it to the statistics array.
  if (!$stats['error']) {
    $stats['widget'] = theme('acquia_purge_status_bar_widget', $stats);
  }

  return drupal_json_output($stats);
}

/**
 * Menu callback to fulfill autocompletes on acquia_purge_manualpurge_form.
 *
 * @returns
 *   A standard Form API form-array.
 */
function acquia_purge_manualpurge_autocomplete($string) {
  $query_limit = 10;
  $matches = array();

  // Overwrite $string and work around Drupal buggingly ignoring slashes.
  $string = implode('/', array_slice(explode('/', $_GET['q']), 6));

  // Scan for node/ lookups as they're quite common.
  if (substr($string, 0, 1) == 'n') {
    $query = db_select('node', 'n')
      ->fields('n', array('nid'))
      ->range(0, $query_limit);

    // Lookup node IDs if the user entered node/, else limit on 15.
    if (strlen($string) > 5) {
      $string = (int) str_replace('node/', '', $string);
      $query->condition('n.nid', '%' . db_like($string) . '%', 'LIKE');
    }

    $results = $query->execute();
    foreach ($results as $nid) {
      $path = 'node/' . $nid->nid;
      $matches[] = $path;
    }
  }

  // Scan for URL aliases when the path module is enabled.
  if (module_exists('path')) {
    $query = db_select('url_alias', 'u')
      ->fields('u', array('alias'))
      ->condition('alias', db_like($string) . '%', 'LIKE')
      ->range(0, $query_limit)
      ->execute();
    foreach ($query as $alias) {
      $matches[] = $alias->alias;
    }
  }

  // Scan for menu_router paths, as long as they don't contain % characters.
  if (module_exists('menu')) {
    $query = db_select('menu_router', 'm')
      ->fields('m', array('path'))
      ->condition('path', db_like($string) . '%', 'LIKE')
      ->condition('path', 'acquia_purge_ajax_processor', '!=')
      ->condition('path', 'admin%', 'NOT LIKE')
      ->range(0, $query_limit)
      ->execute();
    foreach ($query as $path) {
      if (strpos($path->path, '%') !== FALSE) {
        continue;
      }
      $matches[] = $path->path;
    }
  }

  // Sort the values alphabetically.
  sort($matches);

  // Rewrite the matches array by copying the values into keys.
  $clean_url = variable_get('clean_url', 0);
  foreach ($matches as $key => $path) {
    if (!$clean_url) {
      $path = "index.php?q=$path";
    }
    unset($matches[$key]);
    $matches[$path] = $path;
  }

  // Return the output JSON'ed.
  drupal_json_output($matches);
}

/**
 * Menu callback to drupal_get_form; let users manual purge pages.
 *
 * @returns
 *   A standard Form API form-array.
 */
function acquia_purge_manualpurge_form($form, &$form_state) {

  // Show any existing error conditions if they exist.
  if (!_acquia_purge_are_we_allowed_to_purge()) {
    include_once DRUPAL_ROOT . '/includes/install.inc';
    $err = _acquia_purge_get_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR);
    $form['status'] = array(
      '#markup' => theme('status_report', array('requirements' => $err)),
      '#type' => 'item',
      '#title' => t("We're sorry, but due a critical error condition it is "
        . "not possible to use this form. Please contact your administrator."),
    );
    return $form;
  }

  // If things all work fine, continue to build up the form.
  $schemes = _acquia_purge_get_protocol_schemes();
  $domains = _acquia_purge_get_domains();
  $form['description'] = array(
    '#markup' => t('<p>This form allows you to purge one or more paths from your
    Acquia Cloud load balancers. This form is not intended for day-to-day use
    and only meant for site administrators, for instance in emergency cases when
    a outdated copy of a page remains being served. It is highly recommended to
    automate these purges using rules so editorial users can rely on a fully
    fresh site instead of relying on this form.</p>'),
  );

  // Define a list of example paths so users know what they can/should purge.
  $examples = array('&lt;front&gt;', '&lt;front&gt;?page=0', '&lt;front&gt;/',
    'node/1', 'node/1/', 'news?page=0');
  if (_acquia_purge_get('acquia_purge_variations')) {
    $examples = array('&lt;front&gt;', 'node/1', 'news');
  }

  // Build up table rows with domains on the left and examples on the right.
  $rows = array();
  $rowsc = count($domains);
  if (count($examples) > count($domains)) {
    $rowsc = count($examples);
  }
  for ($i = 0; $i < $rowsc; $i++) {
    $row = array();
    $row[0] = isset($domains[$i]) ? $domains[$i] : '';
    $row[1] = isset($examples[$i]) ? $examples[$i] : '';
    $rows[] = $row;
  }

  // Add the guidance table to help the user understand.
  $form['guidancetable'] = array(
    '#theme' => 'table',
    '#header' => array(t('Domains to purge paths on'), t('Example paths')),
    '#rows' => $rows,
    '#empty' => '',
  );

  // Because we have many fields with the same values, we have to set
  // #tree to be able to access them.
  $form['#tree'] = TRUE;

  // We'll group all paths both in a HTML wrapper and logically in the form.
  $title = t('Paths to be purged:');
  $form['paths'] = array(
    '#prefix' => '<div id="paths-wrapper"><p><h3>' . $title . '</h3><ul>',
    '#suffix' => '</ul></p></div>',
  );

  // Fill paths with the right amount of form items according to num_paths.
  $ac_path = 'admin/config/development/performance/manualpurge/autocomplete';
  $prefix = sprintf('<b>%s://%s%s</b>', $schemes[0], $domains[0], base_path());
  if (empty($form_state['num_paths'])) {
    $form_state['num_paths'] = 1;
  }
  for ($i = 0; $i < $form_state['num_paths']; $i++) {
    $form['paths']['path'][$i] = array(
      '#type' => 'textfield',
      '#autocomplete_path' => $ac_path,
      '#field_prefix' => $prefix,
      '#size' => 30,
      '#prefix' => '<li>',
      '#suffix' => '</li>',
    );
  }

  // Declare the add and remove buttons.
  $form['paths']['add_path'] = array(
    '#type' => 'submit',
    '#value' => t('Add'),
    '#submit' => array('acquia_purge_manualpurge_form_add_one'),
    '#ajax' => array(
      'callback' => 'acquia_purge_manualpurge_form_callback',
      'wrapper' => 'paths-wrapper',
    ),
  );
  if ($form_state['num_paths'] > 1) {
    $form['paths']['remove_path'] = array(
      '#type' => 'submit',
      '#value' => t('Remove last item'),
      '#submit' => array('acquia_purge_manualpurge_form_remove_one'),
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'callback' => 'acquia_purge_manualpurge_form_callback',
        'wrapper' => 'paths-wrapper',
      ),
    );
  }

  // Render the submit button and return the form.
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t("I know the risks, purge!"),
  );
  return $form;
}

/**
 * Callback to drupal_get_form: the AJAX callback to paths-wrapper.
 */
function acquia_purge_manualpurge_form_callback($form, $form_state) {
  return $form['paths'];
}

/**
 * Callback to drupal_get_form: add a path textfield.
 */
function acquia_purge_manualpurge_form_add_one($form, &$form_state) {
  $form_state['num_paths']++;
  $form_state['rebuild'] = TRUE;
}

/**
 * Callback to drupal_get_form: remove a path textfield.
 */
function acquia_purge_manualpurge_form_remove_one($form, &$form_state) {
  if ($form_state['num_paths'] > 1) {
    $form_state['num_paths']--;
  }
  $form_state['rebuild'] = TRUE;
}

/**
 * Callback to drupal_get_form: validate the form.
 */
function acquia_purge_manualpurge_form_validate($form, &$form_state) {
  foreach ($form_state['values']['paths']['path'] as $id => $path) {
    if ($msg = _acquia_purge_input_validate($path)) {
      form_set_error('paths][path][' . $id, $msg);
    }
  }
}

/**
 * Callback to drupal_get_form: handle the form submit.
 */
function acquia_purge_manualpurge_form_submit($form, &$form_state) {
  $paths = $form_state['values']['paths']['path'];

  // Improve the administrative experience by automatically adding variations
  // on the given paths, which includes trailing slash versions and pagination.
  if (_acquia_purge_get('acquia_purge_variations')) {
    _acquia_purge_input_path_variations($paths);
  }

  acquia_purge_purge_paths($form_state['values']['paths']['path']);
}

/**
 * Returns HTML for the Acquia Purge status widget.
 *
 * @param array $variables
 *   An associative array containing:
 *   - total: Total amount of actions initially queued for purging.
 *   - remaining: Remaining number of purge actions still to be processed.
 *   - good: Number of purge actions that have already taken place.
 *   - bad: Number of queue items that failed purging.
 *   - percent: Value between 0-100 representing the progress percentage.
 *   - running: Whether URLs are being purged or not.
 *   - purgehistory: Array with recently purged URL's.
 *
 * @ingroup themeable
 */
function theme_acquia_purge_status_widget($variables) {
  if ($variables['running']) {
    return theme('acquia_purge_status_bar_widget', $variables);
  }
  else {
    $message = t('Idle, accepting purges');
    return "<div class=\"messages status\">\n$message</div>\n";
  }
}

/**
 * Returns HTML for the Acquia Purge progress bar widget.
 *
 * @param array $variables
 *   An associative array containing:
 *   - total: Total amount of actions initially queued for purging.
 *   - remaining: Remaining number of purge actions still to be processed.
 *   - good: Number of purge actions that have already taken place.
 *   - bad: Number of queue items that failed purging.
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
