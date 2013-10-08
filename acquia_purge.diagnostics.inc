<?php

/**
 * @file
 * Self-test diagnostic test functions for _acquia_purge_get_diagnosis().
 */

/**
 * Test whether the current hosting environment is Acquia Cloud.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_acquiacloud($t) {
  $site = _acquia_purge_get_site_name();
  $test = array('title' => $t('Hosting environment'));
  if (_acquia_purge_are_we_on_acquiacloud()) {
    $test['value'] = $t('Acquia Cloud: @site', array('@site' => $site));
    $test['severity'] = REQUIREMENT_OK;
  }
  else {
    $test['value'] = $t('Third-party environment.');
    $test['description'] = $t('You are not running on Acquia Cloud, this is a
      mandatory requirement for this module. The module will not process
      scheduled purges, once deployed scheduled purges will be processed.');
    $test['severity'] = REQUIREMENT_ERROR;
  }
  return $test;
}

/**
 * Test the amount of load balancers configured.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_balancers($t) {
  $balancers = _acquia_purge_get_balancers();
  $balancers_c = count($balancers);
  $test = array(
    'value' => implode(', ', $balancers),
    'title' => $t('Load balancers')
  );

  // Determine whether the amount of load balancers is healthy.
  if (!$balancers_c) {
    $test['value'] = $t('No load balancers detected.');
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t('You should be assigned more then one load
      balancer, please contact Acquia Support!');
  }
  elseif ($balancers_c < 2) {
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t('This situation should not occur, please contact
      Acquia Support!');
  }
  elseif ($balancers_c >= 6) {
    $test['severity'] = REQUIREMENT_WARNING;
    $test['description'] = $t('Your website is running behind @no load
      balancers, which might put drastic stress on your database. Please pay
      attention to the number of items in the queue table.',
      array('@no' => $balancers_c));
  }
  else {
    $test['severity'] = REQUIREMENT_OK;
  }
  return $test;
}

/**
 * Test against known conflicting modules such as varnish, boost and purge.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_conflicts($t) {
  $test = array(
    'severity' => REQUIREMENT_OK,
    'value' => $t('No issues detected.'),
    'title' => $t('Module conflicts')
  );

  // Detect expire's "Include base URL in expires" setting, which causes issues.
  if (variable_get('expire_include_base_url', FALSE)) {
    $test['value'] = 'expire';
    $test['severity'] = REQUIREMENT_WARNING;
    $test['description'] = $t("We detected that you enabled the 'Include base
      URL in expires' setting offered by the expire module, this is known to
      (potentially) cause issues. The setting causes hook_expire_cache() to
      be given full urls that breaks its own API and Acquia Purge isn't always
      able to strip these. Check your logs to see if purges execute properly.");
  }

  // Detect the existence of the Boost module which is incompatible.
  if (module_exists('boost')) {
    $test['value'] = 'boost';
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t("Your site has the boost module enabled which is
      known to cause issues on Acquia Cloud. Because of its heavy interactions
      with the underlying file system it will destabilize your site.");
  }

  // Detect the existence of the Purge module which is incompatible for now.
  if (module_exists('purge')) {
    $test['value'] = 'purge';
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t("Your site has the Purge module enabled which
      is incompatible with this version of Acquia Purge. However, this module
      will become dependent on it in the future once both modules play well.");
  }

  // Detect if the Varnish module is enabled, which isn't necessary.
  if (module_exists('varnish')) {
    $test['value'] = 'varnish';
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t("Your site runs with the varnish module enabled,
      which is known to not work on Acquia Cloud. As Acquia Purge does its work
      already for you we strongly encourage you to disable it.");
  }

  return $test;
}

/**
 * Test whether the amount of domain names is healthy.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_domains($t) {
  $domains_link = 'http://drupalcode.org/project/acquia_purge.git/blob_plain/';
  $domains_link .= 'refs/heads/7.x-1.x:/MULTISITE.txt';
  $domains = _acquia_purge_get_domains();
  $domains_c = count($domains);
  $test = array(
    'value' => implode(', ', $domains),
    'title' => $t('Purged domains'),
    'description' => $t('The domains for which content gets cleared from your
      load balancers. Every domain name multiplies the purging work to be done,
      it is therefore important to <a href="!link" target="_blank">specify your
      domains</a> when the automatically detected list is too long or
      incorrect.', array('!link' => $domains_link))
  );

  // Start evaluating the list of domain names.
  if (!$domains_c) {
    $test['value'] = $t('0 domains detected.');
    $test['severity'] = REQUIREMENT_ERROR;
  }
  elseif ($domains_c < 4) {
    $test['severity'] = REQUIREMENT_OK;
  }
  elseif (($domains_c > 4) && ($domains_c < 6)) {
    $test['severity'] = REQUIREMENT_WARNING;
    $test['description'] = $t('The domains for which content gets cleared from
      your load balancers. Because you have @no domain names there is a
      <b>high risk</b> that purging your site will put stress on your database
      server, it is <b>strongly recommended</b> to <a href="!link"
      target="_blank">specify your domains</a> to not exceed 4 domains.',
      array('!link' => $domains_link, '@no' => $domains_c));
  }
  else {
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t('The domains for which content gets cleared from
      your load balancers. Because you have @no domain names there is a
      <b>very high risk</b> that purging your site will put stress on your database
      server, it is <b>urgently recommended</b> to <a href="!link"
      target="_blank">specify your domains</a> to not exceed 4 domains.',
      array('!link' => $domains_link, '@no' => $domains_c));
  }
  return $test;
}

/**
 * Test if anonymous page caching is enabled.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_page_cache($t) {
  $cache = variable_get('cache', 0);
  $test = array(
    'severity' => $cache ? REQUIREMENT_OK : REQUIREMENT_ERROR,
    'value' => $cache ? $t('Enabled') : $t('Disabled'),
    'title' => $t('Cache pages for anonymous users'));

  // Explain why page caching needs to be enabled.
  if (!$cache) {
    $test['description'] = $t('Drupal page caching for anonymous visitors is of
      extreme importance to every website that gets visitors, including low
      traffic sites visited by search engines for instance. Once it is enabled
      your load balancer will be instructed to start caching pages to offload
      traffic from your web servers, the more traffic served directly from your
      load balancer, the better!');
  }

  return $test;
}

/**
 * Test if the page_cache_maximum_age setting is configured in a healthy way.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_page_cache_age($t) {
  $age = variable_get('page_cache_maximum_age', 0);
  $test = array(
    'value' => $t('@age hours', array('@age' => round($age/60/60, 2))),
    'title' => $t('Expiration of cached pages'));

  // Everything below 1 hour is way too short.
  if ($age < 3600) {
    $test['severity'] = REQUIREMENT_ERROR;
    $test['description'] = $t('This is a Drupal core setting that determines
      how long external caches (like load balancers) are instructed to keep a
      cached copy of all content served directly by Drupal. Your current setting
      is far below the 6 hours that is advised in combination with Acquia Purge
      and causes your web servers to serve the majority of traffic instead of
      your load balancer. The higher this setting and the more accurate your
      site gets automatically purged, the better it will perform!');
  }

  // Above 6 hours is safe, so everybody below will be warned!
  elseif (($age > 3600) && ($age < 21600)) {
    $test['severity'] = REQUIREMENT_WARNING;
    $test['description'] = $t('This is a Drupal core setting that determines
      how long external caches (like load balancers) are instructed to keep a
      cached copy of all content served directly by Drupal. Your current setting
      is below the 6 hours that is advised in combination with Acquia Purge
      and causes your web servers to serve the majority of traffic instead of
      your load balancer. The higher this setting and the more accurate your
      site gets automatically purged, the better it will perform!');
  }

  // Everything above 6 hours is good although longer is still better obviously.
  else {
    $test['severity'] = REQUIREMENT_OK;
  }

  return $test;
}

/**
 * Report on the current status of the system.
 *
 * @param $t
 *   Name of the t() function to call.
 *
 * @return
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity: REQUIREMENT_INFO, _OK, _WARNING, _ERROR
 */
function _acquia_purge_get_diagnosis_status($t) {
  $stats = _acquia_purge_queue_stats();
  $test = array(
    'severity' => REQUIREMENT_INFO,
    'value_plain' => $t('Idle, accepting purges'),
    'value' => $stats['running'] ? $t('Running') : $t('Idle, accepting purges'),
    'title' => $t('Status'));

  // Explain why page caching needs to be enabled.
  if ($stats['running']) {
    $test['description'] = theme('acquia_purge_status_bar_widget', $stats);
    $test['value_plain'] = $t("Site content is being refreshed, please wait "
      . "a moment... @remaining items to go...",
      array('@remaining' => $stats['remaining']));
  }

  return $test;
}
