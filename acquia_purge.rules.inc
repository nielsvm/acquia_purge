<?php

/**
 * @file acquia_purge.rules.inc
 *   Integrate with the rules project by providing a purge action.
 * @author
 *   Niels van Mourik - Acquia Client Advisory <niels.vanmourik@acquia.com>
 */

/**
 * Implements hook_rules_action_info().
 */
function acquia_purge_rules_action_info() {
  return array(
    '_acquia_purge_action_purge' => array(
      'label' => t('Purge a path from Varnish on Acquia Cloud'),
      'group' => t('System'),
      'parameter' => array(
        'path' => array(
          'type' => 'text',
          'label' => t('Path'),
          'description' => t('The Drupal path that needs to be purged as relative from the document root of the site. When provided multiple lines, multiple paths will be purged at once. Examples are "node/1" and "news" and there is no need to add a slash at the beginning or end.')
        ),
      )
    ),
  );
}

/**
 * Action callback to the "Purge a path from Varnish on Acquia Cloud" rule.
 */
function _acquia_purge_action_purge($configured_paths) {
  $paths = array();


  // As users can enter these paths, we are treating them as tainted input.
  foreach (explode("\n", $configured_paths) as $configured_path) {
    $path = array();

    // We split the trimmed path on / and reassemble the path to work around
    // starting or trailing slashes and other nasty things.
    foreach (explode('/', trim($configured_path)) as $path_indice) {
      if (!empty($path_indice)) {
        $path[] = $path_indice;
      }
    }

    // Reassemble the path and add it to the paths collection.
    $paths[] = implode('/', $path);
  }

  // Relay the purging of these paths to our API helper and we are done here.
  acquia_purge_purge_paths($paths);
}