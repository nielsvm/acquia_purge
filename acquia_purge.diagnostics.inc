<?php

/**
 * @file
 * Diagnostic self-tests and reports that aim to prevent runtime misery.
 */

/**
 * Status.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_status($t, $service) {
  $passivemode = _acquia_purge_variable('acquia_purge_passivemode');
  $errorlimit = _acquia_purge_variable('acquia_purge_errorlimit');
  $stats = $service->stats();
  $test = array(
    'title' => $t('Status'),
    'value' => $passivemode ? $t('Idle (passive mode)') : t('Idle'),
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
  );

  // When the user did not set a static integer as error limit, we calculate
  // it dynamically with limit = factor^3, with 20 as lowest limit. The higher
  // the factor is, the more tolerant our limit is. Limits for factors 1-14:
  // 20, 20, 27, 64, 125, 216, 343, 512, 729, 1000, 1331, 1728, 2197, 2744
  if (!is_int($errorlimit)) {
    $factor = $service->capacity()->httpRequestsFactor();
    $errorlimit = ($factor <= 2) ? 20 : ceil(pow($factor, 3));
  }
  if ($stats['bad'] > $errorlimit) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t(
        'Acquia Purge failed over @limit times (@bad), since the last time the'
      . ' queue was processed. To avoid crashing your site with endless queue'
      . ' processing, cache invalidation has been disabled. We recommend you to'
      . " ask your site maintainer to check Drupal's watchdog logs for errors,"
      . ' before lifting the blockade with "drush ap-forget".',
      array('@limit' => $errorlimit, '@bad' => $stats['bad']));
    return $test;
  }

  // Report status if the system is running.
  if ($stats['running']) {
    $test['description'] = theme('acquia_purge_status_bar_widget', $stats);
    if ($stats['locked']) {
      $test['value'] = $test['value_plain'] = $t('Processing');
    }
    else {
      $test['value'] = $test['value_plain'] = $t('Items queued');
    }
  }

  return $test;
}

/**
 * Acquia Purge.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_acquia_purge($t, $service) {
  $test = array(
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
    'value' => ACQUIA_PURGE_VERSION,
    'title' => $t('Acquia Purge'),
  );

  // Detect the existence of the Boost module which is incompatible.
  if (module_exists('boost')) {
    $test['value'] = $t('Conflicts with: @m', array('@m' => 'boost'));
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Your site has the boost module enabled which is'
      . ' known to cause issues on Acquia Cloud. Because of its heavy'
      . ' interactions with the file system it will destabilize your site.');
  }

  // Detect the existence of the Purge module which is incompatible for now.
  if (module_exists('purge')) {
    $test['value'] = $t('Conflicts with: @m', array('@m' => 'purge (d7)'));
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Your site has the Purge module enabled which'
      . ' is incompatible with Acquia Purge for Drupal 7. However, the version'
      . ' for Drupal 8 depends on it, but please remove it on this setup!');
  }

  // Detect if the Varnish module is enabled, which isn't necessary.
  if (module_exists('varnish')) {
    $test['value'] = $t('Conflicts with: @m', array('@m' => 'varnish'));
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Your site runs with the varnish module enabled,'
      . ' which is known to not work on Acquia Cloud. As Acquia Purge does its'
      . ' work already for you we strongly encourage you to remove it.');
  }

  // Detect if the Domain Access module is enabled, which is recipe for trouble.
  if (module_exists('domain')) {
    $test['value'] = $t('Conflicts with: @m', array('@m' => 'Domain Access'));
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Domain Access allows operating your site on'
      . ' multiple domain names and slicing content among those domains. This'
      . ' conflicts with Acquia Purge as editors could update a piece of'
      . ' content on one domain, which would then get cleared on all domains'
      . ' that Acquia Purge operates on. On top of this, Acquia Purge operates'
      . ' a queue which means that DA-domain information gets lost after saving'
      . ' content. Therefore, unless you are not splitting content with DA, you'
      . ' are recommended to reconsider your architecture.');
  }

  // Detect if the shield module is in use.
  if (module_exists('shield')) {
    $test['value'] = $t('Conflicts with: @m', array('@m' => 'Shield'));
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('The Shield module is in use on your site and'
      . ' protects your content with a password. Because Varnish caches these'
      . ' items differently, the instructions Acquia Purge sends to Varnish'
      . " will not work. This inconvenience requires you to either disable"
      . ' Acquia Purge or enable the passivemode setting. Please see'
      . ' INSTALL.md and https://www.drupal.org/node/2642458 for more info.');
  }

  return $test;
}

/**
 * Acquia environment.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_acquia_environment($t, $service) {
  $test = array('title' => $t('Acquia environment'));
  if ($service->hostingInfo()->isThisAcquiaCloud()) {
    $test['value'] = $t('Acquia Cloud (@name.@env)',
      array(
        '@name' => $service->hostingInfo()->getSiteName(),
        '@env' => $service->hostingInfo()->getSiteEnvironment(),
      )
    );
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_INFO;
  }
  else {
    $test['value'] = $t('Third-party');
    $test['description'] = $t('Acquia Purge only works on Acquia Cloud and has'
      . ' disabled itself as this environment appears different. Any items in'
      . ' the queue will get processed as soon as you deployed your site.');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
  }
  return $test;
}

/**
 * Acquia auth token.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_acquia_auth_token($t, $service) {
  $test = array(
    'title' => $t('Acquia auth token'),
    'value' => $service->hostingInfo()->getBalancerToken(),
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
  );
  if ($service->oddities()->has('405')) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t(
      'Acquia Purge received HTTP 405 (not allowed) responses from one of'
      . ' your load balancers! What this suggests is that your authentication'
      . ' token is (or has been) invalid. If cache invalidation constantly'
      . ' appears to fail, please contact Acquia support.'
    );
  }
  elseif (!strlen($service->hostingInfo()->getBalancerToken())) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Empty token configured!');
  }
  return $test;
}

/**
 * Loaded state storage.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_backends_statestorage($t, $service) {
  $test = array('title' => $t('Loaded state storage'));
  if ($service->hostingInfo()->isMemcachedUsed()) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_INFO;
    $test['value'] = 'Memcached';
  }
  else {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['value'] = $t('File: @p', array('@p' => ACQUIA_PURGE_STATE_FILE));
    $test['description'] = $t('It is highly recommended to setup Memcached for'
        . ' your website. Cache invalidation will benefit from it, but the'
        . ' overall benefit to your site is far larger. https://docs.acquia.com'
        . '/acquia-cloud/performance/memcached');
  }
  return $test;
}

/**
 * Loaded processors.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_backends_processor($t, $service) {
  $test = array(
    'title' => $t('Loaded processors'),
    'value' => array($t('Ajax')),
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
  );

  // Add tests for lateruntime and cron processing modes, when enabled.
  if (_acquia_purge_variable('acquia_purge_lateruntime')) {
    $test['value'][] = $t('Runtime');
  }
  if (_acquia_purge_variable('acquia_purge_cron')) {
    $test['value'][] = $t('Cron');

    // Raise a warning when it was too long ago since cron ran.
    $cron_last = variable_get('cron_last');
    if (!is_numeric($cron_last)) {
      $cron_last = variable_get('install_time', 0);
    }
    if (REQUEST_TIME - $cron_last > 3600) {
      $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
      $test['description'] = $t('It has been longer than one hour since cron ran'
        . ' for the last time, which puts your site at risk. As Acquia Purge'
        . ' has cron processing enabled, it assumes processing of its queue.'
        . ' When this is not frequent enough, the queue can reach dangerous'
        . ' levels and eventually even lead to a safety shutdown.');
    }
  }

  $test['value'] = implode(', ', $test['value']);
  return $test;
}

/**
 * Loaded executors.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_backends_executor($t, $service) {
  $executors = $service->executorIds();
  foreach ($executors as $n => $id) {
    $executors[$n] = str_replace('AcquiaPurgeExecutor', '', $id);
  }
  $test = array(
    'value' => implode(', ', $executors),
    'title' => $t('Loaded executors'),
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
  );
  if (empty($executors)) {
    $test['value'] = $t('None');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t("Can't work without loaded executors!");
  }
  return $test;
}

/**
 * Loaded queue.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_backends_queue($t, $service) {
  $test = array(
    'title' => $t('Loaded queue'),
    'value' => t('Database'),
    'severity' => ACQUIA_PURGE_SEVLEVEL_INFO,
  );
  if (_acquia_purge_variable('acquia_purge_smartqueue')) {
    $test['value'] = t('Smart database (drops old items)');
  }
  return $test;
}

/**
 * Invalidated schemes.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_inv_schemes($t, $service) {
  $schemes = $service->hostingInfo()->getSchemes();
  $test = array(
    'title' => $t('Invalidated schemes'),
    'value' => strtoupper(implode(', ', $schemes)),
  );

  // Test if no protocol schemes are to be invalidated.
  if (empty($schemes)) {
    $test['value'] = $t('None');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('It seems as if you have put both '
    . ' <b>acquia_purge_http</b> and <b>acquia_purge_https</b> settings to'
    . ' <b>FALSE</b> and thus disabled, which leads to a situation'
    . ' that does not work. Enable either one of them for Acquia Purge'
    . ' to continue to operate.');
  }

  // Test for the best possible scenario, either http:// OR https:// enabled.
  elseif (count($schemes) === 1) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
  }

  // Test for the Molotov cocktail scenario, two schemes with >4 domains.
  elseif ((count($schemes) === 2) && (count($service->hostingInfo()->getDomains()) > 4)) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('You are clearing both <b>http://</b> as'
    . ' <b>https://</b> paths on your site with more than 4 domain names.'
    . ' Acquia Purge has self-shutdown to prevent worse from happening as'
    . ' two protocol schemes with this many domain names can likely bring'
    . ' down your website at some point. Its better to either only wipe'
    . ' https:// or by reducing unnecessary domain names, for instance'
    . ' bare domains that redirect. See INSTALL.md and DOMAINS.md for more'
    . ' information on tuning.');
  }

  // Test for the lesser dangerous cocktail, just two schemes.
  elseif (count($schemes) === 2) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('You are clearing both <b>http://</b> as'
    . ' <b>https://</b> paths on your site, which can be dangerous. Especially'
    . ' if you have more than 1-2 domains as this will lead in the double'
    . ' amount of work that gets done. Keep a close eye on the queue and'
    . ' consider delivering all your traffic off https:// (by doing a full'
    . ' -site redirect) and to disable http:// clearing, see INSTALL.md.');
  }

  return $test;
}

/**
 * Invalidated domains.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_inv_domains($t, $service) {
  $allriskmode = _acquia_purge_variable('acquia_purge_allriskmode');
  $hosting_info = $service->hostingInfo();
  $domains_link = drupal_get_path('module', 'acquia_purge') . '/DOMAINS.md';
  $domains_link = url($domains_link);
  $domains = $hosting_info->getDomains();
  $domains_c = count($domains);
  $test = array(
    'value' => implode(', ', $domains),
    'value' => $t('@mode: @domains', array(
        '@domains' => implode(' ', $domains),
        '@mode' => $hosting_info->areDomainsHardcoded() ? $t('Manual') : $t('Automatic')
      )),
    'title' => $t('Invalidated domains'),
    'description' => $t('The domains for which content gets cleared from your'
      . ' load balancers. Every domain name multiplies the work to be done,'
      . ' it is therefore important to <a href="!link" target="_blank">'
      . 'specify your domains</a> when the automatically detected list exceeds'
      . ' 4 domains or when it is incorrect.', array('!link' => $domains_link)),
  );

  // One cannot have wildcards in domain names, test for this.
  $wildcard_domains_found = FALSE;
  foreach ($domains as $domain) {
    if (strpos($domain, '*') !== FALSE) {
      $wildcard_domains_found = $domain;
      break;
    }
  }

  // Evaluate domain names configuration and kick off warnings or even a full
  // block when the situation asks for it.
  if (!$domains_c) {
    $test['value'] = $t('0 domains detected.');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('No domains have been detected which implies that'
      . ' something is seriously wrong. Pending purges will not be processed.');
  }
  elseif ($wildcard_domains_found) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('The domain "@baddomain" has a asterisk in it,'
      . ' which implies that its subdomains require clearing. However, this is'
      . ' unfortunately not supported by Acquia Cloud and therefore will lead'
      . ' to purge failure. Please remove this domain from your configuration.',
      array('@baddomain' => $wildcard_domains_found));
  }
  elseif ((php_sapi_name() === 'cli') && in_array('default', $domains)) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('The domain name "default" has been found'
      . ' indicating that you are running via Drush. Either you will need to'
      . ' specify your domains or provide --uri for the right site.');
  }
  elseif ($domains_c <= 6) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
    unset($test['description']);
  }
  elseif (($domains_c > 6) && ($domains_c <= 8)) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Because you have @no domain names there is a'
      . ' <b>high risk</b> that clearing your site will put stress on your'
      . ' servers, it is <b>strongly recommended</b> to <a href="!link"'
      . ' target="_blank">specify your domains</a> to not exceed 6 domains.',
      array('!link' => $domains_link, '@no' => $domains_c));
  }
  elseif ($allriskmode) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Because you have @no domain names there is a'
      . ' <b>high risk</b> that clearing your site will put stress on your'
      . ' servers, it is <b>strongly recommended</b> to <a href="!link"'
      . ' target="_blank">specify your domains</a> to not exceed 6 domains.',
      array('!link' => $domains_link, '@no' => $domains_c));
  }
  else {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Because you have @no domain names there is a'
      . ' <b>very high risk</b> that clearing your site will put stress on your'
      . ' servers, it is <b>strongly recommended</b> to <a href="!link"'
      . ' target="_blank">specify your domains</a> to not exceed 6 domains. To'
      . ' prevent system failure, pending purges will not be processed!',
      array('!link' => $domains_link, '@no' => $domains_c));
  }

  return $test;
}

/**
 * Invalidated balancers.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_inv_balancers($t, $service) {
  $test = array(
    'value' => implode(', ', $service->hostingInfo()->getBalancerAddresses()),
    'title' => $t('Invalidated balancers'),
  );

  // When HTTP_X_GEO_COUNTRY is detected, register it as VCL oddity.
  if (isset($_SERVER['HTTP_X_GEO_COUNTRY'])) {
    if (strlen($_SERVER['HTTP_X_GEO_COUNTRY'])) {
      $service->oddities()->add('geoip');
    }
  }

  // Evaluate the sanity of the amount of load balancers.
  $balancers_c = count($service->hostingInfo()->getBalancerAddresses());
  if (!$balancers_c) {
    $test['value'] = $t('No load balancers detected.');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('There are no load balancers detected which makes'
      . ' it impossible to purge your site. Please contact Acquia Support!');
  }
  elseif ($balancers_c < 2) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('You have only one load balancer, this means your'
      . ' site can not be failed over by us. Please contact Acquia Support.');
  }
  elseif ($balancers_c >= 6) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Your website is running behind @no load'
      . ' balancers, which will put severe stress on your database. Please pay'
      . ' attention to the number of items in the queue table.',
      array('@no' => $balancers_c));
  }
  elseif ($service->oddities()->has('geoip')) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t(
      'The HTTP_X_GEO_COUNTRY variable has been detected, which indicates'
      . ' that you have GeoIP enabled. Acquia Purge automatically switched to'
      . ' issuing BAN-requests. However, this only works behind a Varnish 4'
      . ' load balancer. When you are seeing HTTP 501 errors in your logs, then'
      . ' you require upgraded load balancers. Acquia is in the process to'
      . ' upgrade its entire platform.'
    );
  }
  elseif ($service->oddities()->has('403')) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t(
        'Acquia Purge received HTTP 403 (forbidden) responses from one of your'
      . ' load balancers, this is abnormal behavior! This could suggest that'
      . ' your site runs behind a customized VCL file which likely hinders the'
      . ' proper functioning of this module. Please contact Acquia support with'
      . ' this message to get further guidance on how to resolve the problem.'
    );
  }
  else {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
  }
  return $test;
}

/**
 * Expire module.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_expire_module($t, $service) {
  $test = array('title' => $t('Expire module'));

  // If expire is not installed, Acquia Purge can still work as API.
  if (!module_exists('expire')) {
    $test['value'] = $t('Not installed!');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('The expire module is not present, which only'
      . ' makes sense if you want to use Acquia Purge as pure API (and have'
      . ' removed expire from acquia_purge.info). If you want your content to'
      . ' be cleared when is changing, enable it back on again.');
    return $test;
  }

  // Read out Expire's 'expire_status' variable.
  $statuses = array(
    EXPIRE_STATUS_ENABLED_EXTERNAL => $t('External expiration'),
    EXPIRE_STATUS_ENABLED_INTERNAL => $t('Internal expiration'),
    EXPIRE_STATUS_DISABLED => $t('Disabled'),
    'n/a' => $t('unknown'),
  );
  $status = (int) variable_get('expire_status', EXPIRE_STATUS_DISABLED);
  $status_t = isset($statuses[$status]) ? $statuses[$status] : $statuses['n/a'];

  // Test Expire's 'expire_include_base_url' variable, which should be FALSE.
  if (variable_get('expire_include_base_url', EXPIRE_INCLUDE_BASE_URL)) {
    $test['value'] = $t('Include base URL in expires');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t("This setting must be disabled as Acquia Purge"
      . ' cannot process full URLs but operates on paths instead.');
  }

  // Test Expire's 'expire_debug' variable, which should be FALSE.
  elseif (variable_get('expire_debug', EXPIRE_DEBUG_DISABLED)) {
    $test['value'] = $t('Debugging enabled');
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t("Expire's debugging is enabled which is highly"
      . ' discouraged! Acquia Purge also logs very heavily and together with'
      . ' Expire debugging, this could break down your site!');
  }

  // Test Expire's 'expire_status' variable, which must be external for AP.
  elseif ($status === EXPIRE_STATUS_ENABLED_EXTERNAL) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
    $test['value'] = $t('Delegates URLs to Acquia Purge');
  }
  else {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['value'] = $t('Status: @status', array('@status' => $status_t));
    $test['description'] = $t('The Expire module operates in the "@status" mode'
      . ', this means that Expire will not delegate URLs to Acquia Purge when'
      . ' you are updating content. Change it to "External expiration".',
      array('@status' => $status_t)
    );
  }
  return $test;
}

/**
 * Page cache.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_page_cache($t, $service) {
  $cache = variable_get('cache', 0);
  $cache_backend = $service->hostingInfo()->getPageCacheBackend();
  $test = array(
    'severity' => ACQUIA_PURGE_SEVLEVEL_OK,
    'value' => $cache ? $t('Enabled (@b)', array('@b' => $cache_backend)) : $t('Disabled'),
    'title' => $t('Page cache'),
  );

  // See https://www.drupal.org/node/2330805
  if ((!$cache) && module_exists('authcache')) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
    $test['value'] = $t('Disabled (because of Authcache)');
  }
  elseif (!$cache) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Your site has Drupal page caching disabled,'
      . ' which is of extreme importance to every website that gets traffic.'
      . ' Once enabled your load balancer will be instructed to start serving'
      . ' pages from its cache to offload traffic from your web servers. The'
      . ' more traffic served from your load balancer, the faster your'
      . ' site will be!');
  }
  elseif ($service->hostingInfo()->isPageCacheFake()) {
    $test['value'] = $t("Enabled (DrupalFakeCache, executor disabled)");
  }
  elseif ($service->oddities()->has('wildcards')) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Your site invalidated one or more paths with'
      . ' wildcard characters in them in the past, e.g. "news/*". Because of'
      . ' this, you are now recommended to configure DrupalFakeCache as'
      . " documented on https://www.drupal.org/node/797346. If you don't do"
      . ' this, outdated cached copies will be returned from the page cache'
      . ' for paths you intended to clear with a wildcard.');
  }
  return $test;
}

/**
 * Page caching time (TTL).
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_page_caching_time_ttl($t, $service) {
  $age = (int) variable_get('page_cache_maximum_age', 0);
  $test = array('title' => $t('Page caching time (TTL)'));

  // Set the value label.
  $test['value'] = $t('Disabled');
  if ($age !== 0) {
    $test['value'] = array('@age' => round($age / 60 / 60, 2));
    $test['value'] = $t('@age hours', $test['value']);
  }

  // Determine what "find out more" link we can provide.
  $link = 'http://blog.merge.nl/20120118/how-does-caching-work-drupal';
  if (function_exists('user_access')) {
    if (function_exists('user_access') && user_access('administer site configuration')) {
      $link = url('admin/config/development/performance');
    }
  }


  // Users without a TTL configured won't put our module to much use.
  if ($age === 0) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('The "<a href="!link" target="_blank">expiration'
    . ' of cached pages</a>"-setting of your website is disabled, while we'
    . ' strongly recommend to put it to 6 hours or longer in combination with'
    . ' the Acquia Purge module. The setting determines how long external'
    . ' caches (like our load balancers) are instructed to keep a cached copy'
    . ' of content. With your current setting your load balancer is not being'
    . ' used and your web servers will likely be under constant stress,'
    . ' causing a slow site and  system resources being wasted.',
    array('!link' => $link));
  }

  // We have picked 6 hours as a safe zone to be in, everything above is better!
  elseif ($age < 21600) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('The "<a href="!link" target="_blank">expiration'
    . ' of cached pages</a>"-setting of your website is set to @age, while we'
    . ' strongly recommend to put it to 6 hours or longer in combination with'
    . ' the Acquia Purge module. The setting determines how long external'
    . ' caches (like our load balancers) are instructed to keep a cached copy'
    . ' of content. With your current configuration, your load balancer is not'
    . ' used effectively and your web servers will likely be under stress,'
    . ' causing a slow site and system resources being wasted.',
    array('!link' => $link, '@age' => $test['value']));
  }

  // Everything above 6 hours is good although longer is still better obviously.
  else {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
  }

  return $test;
}

/**
 * Capacity.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_capacity($t, $service) {
  $capacity = $service->capacity();
  $maxitems = $capacity->queueClaimsLimit();
  $factor = $capacity->httpRequestsFactor();
  $test = array(
    'title' => $t('Capacity'),
    'severity' => ACQUIA_PURGE_SEVLEVEL_OK,
    'value' => $t('@items items/batch, slowdown factor=@factorX',
      array('@items' => $maxitems, '@factor' => $factor)),
  );
  return $test;
}

/**
 * Logging.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_logging($t, $service) {
  $test = array(
    'severity' => ACQUIA_PURGE_SEVLEVEL_OK,
    'value' => $t('Successes and failures are logged'),
    'title' => $t('Logging'),
  );
  if (_acquia_purge_variable('acquia_purge_log_success') !== TRUE) {
    $test['value'] = $t('Only failures are logged');
  }
  return $test;
}

/**
 * Queue safety.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_queue_safety($t, $service) {
  $qitems = $service->stats()['remaining'];

  // Define the warning and shutdown thresholds and setup $test.
  $factor   = $service->capacity()->httpRequestsFactor();
  $warning  = ceil(3000 / $factor);
  $shutdown = ceil(10000 / $factor);
  $tvars = array(
    '@qitems' => $qitems,
    '@warning' => $warning,
    '@shutdown' => $shutdown,
  );
  $test = array(
    'title' => $t('Queue safety'),
    'value' => $t('Warning at @warning, shutdown at @shutdown items', $tvars),
  );

  // Test the queue level for these thresholds and act appropriately.
  if ($qitems < $warning) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_OK;
  }
  elseif (($qitems >= $warning) && ($qitems < $shutdown)) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Your queue holds @qitems items now which is'
      . ' regarded as dangerous, as it either means that your are clearing too'
      . ' broadly (domains, http:// and https://) or that your installation is'
      . ' not able to keep up processing. If you are not using cron for'
      . ' processing, consider this now and if you already are, consider a'
      . ' shorter cron interval. If you have many domain names, consider'
      . ' reducing the domains. As safety measure, Acquia Purge will shut'
      . ' itself down once the queue reaches @shutdown items!', $tvars);
  }
  elseif (_acquia_purge_variable('acquia_purge_allriskmode') && ($qitems >= $shutdown)) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_WARNING;
    $test['description'] = $t('Your queue contains @qitems items and crossed'
      . ' the safety limit of @shutdown items, Acquia Purge has not shut itself'
      . ' down because you enabled "all risk mode". Because of this, you are'
      . ' not entitled to Acquia support for any tickets related to queue'
      . ' processing. You are being highly encouraged to lower your slowdown'
      . ' factor by reducing domain names and/or increasing your cron interval'
      . ' or enable cron processing when you have not yet done this.', $tvars);
  }
  elseif ($qitems >= $shutdown) {
    $test['severity'] = ACQUIA_PURGE_SEVLEVEL_ERROR;
    $test['description'] = $t('Your queue contains @qitems items and crossed'
      . ' the safety limit of @shutdown items, Acquia Purge has shut itself'
      . ' down to prevent your system from thrashing. What this means is that'
      . ' you are either clearing too broadly (domains, http:// and https://)'
      . ' or that your installation is not able to keep up. If you are not'
      . ' using cron for processing, set this up now and if you already are,'
      . ' increase your cron interval! If you have many domain names, reduce'
      . ' the list. Clear Varnish manually and run drush ap-forget to clear'
      . ' your queue and unblock the safety shutdown.', $tvars);
  }
  return $test;
}

/**
 * Queue.
 *
 * @param string $t
 *   Name of the t() function to call.
 * @param AcquiaPurgeService $service
 *   The Acquia Purge service.
 *
 * @return array
 *   Associative array with the following elements:
 *   - title: The name of the requirement.
 *   - value: The current value (e.g., version, time, level, etc).
 *   - description: The description of the requirement/status.
 *   - severity:
 *       - ACQUIA_PURGE_SEVLEVEL_INFO
 *       - ACQUIA_PURGE_SEVLEVEL_OK
 *       - ACQUIA_PURGE_SEVLEVEL_WARNING
 *       - ACQUIA_PURGE_SEVLEVEL_ERROR <-- blocks Acquia Purge from executing!
 */
function _acquia_purge_diagnostics_queue($t, $service) {
  $stats = $service->stats();
  if ($stats['remaining'] === 0) {
    $value = $t('Empty');
  }
  else {
    $value = $t('Contains @d items.', array('@d' => $stats['remaining']));
  }
  return array(
    'title' => $t('Queue'),
    'value' => $value,
    'severity' => ACQUIA_PURGE_SEVLEVEL_OK,
  );
}